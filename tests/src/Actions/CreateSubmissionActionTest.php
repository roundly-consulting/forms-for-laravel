<?php

declare(strict_types=1);

use RoundlyConsulting\Forms\Actions\CreateSubmissionAction;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionData;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Tests\testable\CustomSubmission;

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
