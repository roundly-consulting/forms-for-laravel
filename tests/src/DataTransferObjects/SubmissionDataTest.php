<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionData;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionResult;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;

it('maps a sender model onto submission data', function () {
    $form = Form::factory()->create(['key' => 'profile']);
    $group = Group::factory()->for($form)->create(['key' => 'details']);
    $field = Field::factory()->for($form)->for($group)->create(['key' => 'name']);

    $data = SubmissionData::forField($field, 'uuid-1', ['value' => 'Jane'], $group);

    expect($data->uuid)->toBe('uuid-1')
        ->and($data->senderId)->toBe($group->getKey())
        ->and($data->senderType)->toBe(Group::class)
        ->and($data->formId)->toBe($field->form_id)
        ->and($data->groupId)->toBe($field->group_id)
        ->and($data->fieldId)->toBe($field->getKey())
        ->and($data->value)->toBe(['value' => 'Jane']);
});

it('leaves sender fields null when no sender is given', function () {
    $form = Form::factory()->create(['key' => 'profile']);
    $group = Group::factory()->for($form)->create(['key' => 'details']);
    $field = Field::factory()->for($form)->for($group)->create(['key' => 'name']);

    $data = SubmissionData::forField($field, 'uuid-2', ['value' => 'x']);

    expect($data->senderId)->toBeNull()
        ->and($data->senderType)->toBeNull();
});

it('exposes a submission result', function () {
    $now = Carbon::parse('2030-01-01 12:00:00');

    $result = new SubmissionResult(uuid: 'uuid-3', fieldCount: 2, submittedAt: $now);

    expect($result->uuid)->toBe('uuid-3')
        ->and($result->fieldCount)->toBe(2)
        ->and($result->submittedAt)->toBe($now);
});
