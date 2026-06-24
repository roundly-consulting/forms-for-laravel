<?php

declare(strict_types=1);

use RoundlyConsulting\Forms\Actions\CreateSubmissionAction;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionData;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Tests\testable\CustomSubmission;

function makeField(): Field
{
    $form = Form::factory()->create(['key' => 'profile']);
    $group = Group::factory()->for($form)->create(['key' => 'details']);

    return Field::factory()->for($form)->for($group)->create(['key' => 'name']);
}

it('persists a submission with a sender', function () {
    $field = makeField();

    $submission = app(CreateSubmissionAction::class)->execute(
        SubmissionData::forField($field, 'uuid-1', ['value' => 'Jane'], $field->group),
    );

    expect($submission)->toBeInstanceOf(Submission::class)
        ->and($submission->value)->toBe(['value' => 'Jane'])
        ->and($submission->sender_type)->toBe(Group::class)
        ->and($submission->sender_id)->toBe($field->group_id)
        ->and(Submission::query()->where('uuid', 'uuid-1')->exists())->toBeTrue();
});

it('persists a submission without a sender', function () {
    $field = makeField();

    $submission = app(CreateSubmissionAction::class)->execute(
        SubmissionData::forField($field, 'uuid-2', ['value' => 'x']),
    );

    expect($submission->sender_id)->toBeNull()
        ->and($submission->sender_type)->toBeNull();
});

it('honours the submission model override', function () {
    config()->set('forms.models.submission', CustomSubmission::class);

    $field = makeField();

    $submission = app(CreateSubmissionAction::class)->execute(
        SubmissionData::forField($field, 'uuid-3', ['value' => 'y']),
    );

    expect($submission)->toBeInstanceOf(CustomSubmission::class);
});
