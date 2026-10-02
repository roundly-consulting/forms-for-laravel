<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Forms\Actions\CreateSubmissionAction;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionData;
use RoundlyConsulting\Forms\Enums\SubmissionStatus;
use RoundlyConsulting\Forms\Exceptions\SubmissionNotFoundException;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\FormSubmission;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Tests\testable\CustomSubmission;
use RoundlyConsulting\Forms\Tests\testable\Reviewer;

/**
 * A real uuid per case. These read as 'uuid-1'/'uuid-2'/'uuid-3' before this row, which is
 * not a uuid at all: `submissions.uuid` is a real uuid column, so Postgres rejects the
 * insert outright (`SQLSTATE[22P02] invalid input syntax for type uuid`). SQLite stores the
 * column as text and accepts any string, which is the only reason these passed.
 */
function submissionUuid(int $n): string
{
    return sprintf('019f6eef-76b8-73b9-ab3c-78c1a649650%d', $n);
}

function makeField(): Field
{
    $form = Form::factory()->create(['key' => 'profile']);
    $group = Group::factory()->for($form)->create(['key' => 'details']);

    return Field::factory()->for($form)->for($group)->create(['key' => 'name']);
}

it('persists a submission with a sender', function () {
    $field = makeField();

    $submission = app(CreateSubmissionAction::class)->execute(
        SubmissionData::forField($field, submissionUuid(1), ['value' => 'Jane'], $field->group),
    );

    expect($submission)->toBeInstanceOf(Submission::class)
        ->and($submission->value)->toBe(['value' => 'Jane'])
        ->and($submission->sender_type)->toBe(Group::class)
        ->and($submission->sender_id)->toBe($field->group_id)
        ->and(Submission::query()->where('uuid', submissionUuid(1))->exists())->toBeTrue();
});

it('persists a submission without a sender', function () {
    $field = makeField();

    $submission = app(CreateSubmissionAction::class)->execute(
        SubmissionData::forField($field, submissionUuid(2), ['value' => 'x']),
    );

    expect($submission->sender_id)->toBeNull()
        ->and($submission->sender_type)->toBeNull();
});

it('honours the submission model override', function () {
    config()->set('forms.models.submission', CustomSubmission::class);

    $field = makeField();

    $submission = app(CreateSubmissionAction::class)->execute(
        SubmissionData::forField($field, submissionUuid(3), ['value' => 'y']),
    );

    expect($submission)->toBeInstanceOf(CustomSubmission::class);
});

/*
 * Review fixes (2026-09-28) — a row written directly (imports, seeds) is filed under the
 * whole-submission aggregate of its uuid, created on first use, so it reads, finalizes and
 * reviews like any other submission instead of `Forms::submission($uuid)` not finding it.
 */

it('files directly written rows under one submission that reads and reviews', function () {
    config()->set('forms.approvals.enabled', true);
    Schema::create('reviewers', function (Blueprint $table): void {
        $table->id();
        $table->timestamps();
    });

    $name = makeField();
    $email = Field::factory()->for($name->form)->for($name->group)->create(['key' => 'email']);

    $first = Forms::createSubmission($name, ['value' => 'Ann'], bypassClosed: true);
    $second = Forms::createSubmission($email, ['value' => 'ann@example.com'], uuid: $first->uuid, bypassClosed: true);

    $aggregate = FormSubmission::query()->sole();

    expect($first->form_submission_id)->toBe($aggregate->getKey())
        ->and($second->form_submission_id)->toBe($aggregate->getKey())
        ->and($aggregate->uuid)->toBe($first->uuid)
        ->and($aggregate->status)->toBe(SubmissionStatus::Final)
        ->and(Forms::submission($first->uuid)->get()->values)->toBe(['name' => 'Ann', 'email' => 'ann@example.com']);

    Forms::review($first->uuid)->requiring([Reviewer::query()->create()])->open();

    expect($aggregate->fresh()?->status)->toBe(SubmissionStatus::Pending);
});

it('refuses to file a directly written row under another form\'s submission, a draft or a malformed uuid', function () {
    $field = makeField();
    $otherForm = Form::factory()->create(['key' => 'other']);
    $other = Field::factory()->for($otherForm)->for(Group::factory()->for($otherForm))->create();

    $foreign = Forms::createSubmission($other, ['value' => 'x'], bypassClosed: true);
    $draft = FormSubmission::factory()->for($field->form)->create(['status' => SubmissionStatus::Draft]);

    foreach ([$foreign->uuid, $draft->uuid, 'not-a-uuid'] as $uuid) {
        expect(fn () => Forms::createSubmission($field, ['value' => 'y'], uuid: $uuid, bypassClosed: true))
            ->toThrow(SubmissionNotFoundException::class);
    }

    expect(Submission::query()->where('field_id', $field->getKey())->exists())->toBeFalse();
});
