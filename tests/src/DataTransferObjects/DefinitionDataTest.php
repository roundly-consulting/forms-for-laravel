<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Forms\DataTransferObjects\FieldDefinitionData;
use RoundlyConsulting\Forms\DataTransferObjects\FormDefinitionData;
use RoundlyConsulting\Forms\DataTransferObjects\GroupDefinitionData;

it('constructs a field definition with defaults', function () {
    $field = new FieldDefinitionData(key: 'email', name: 'Email');

    expect($field->key)->toBe('email')
        ->and($field->name)->toBe('Email')
        ->and($field->type)->toBe('text')
        ->and($field->help)->toBeNull()
        ->and($field->autofill)->toBeNull()
        ->and($field->options)->toBeNull()
        ->and($field->validations)->toBeNull()
        ->and($field->order)->toBeNull(); // null = its position in the group
});

it('builds a field definition from a loose array', function () {
    $field = FieldDefinitionData::fromArray([
        'key' => 'country',
        'name' => 'Country',
        'type' => 'select',
        'help' => 'Pick one',
        'autofill' => 'App\\Autofill\\Country',
        'options' => ['CZ' => 'Czechia'],
        'validations' => ['required'],
        'order' => 3,
    ]);

    expect($field->type)->toBe('select')
        ->and($field->help)->toBe('Pick one')
        ->and($field->autofill)->toBe('App\\Autofill\\Country')
        ->and($field->options)->toBe(['CZ' => 'Czechia'])
        ->and($field->validations)->toBe(['required'])
        ->and($field->order)->toBe(3);
});

it('round-trips a nested form definition from an array', function () {
    $expiresAt = Carbon::parse('2030-01-01 00:00:00');

    $form = FormDefinitionData::fromArray([
        'key' => 'contact',
        'name' => 'Contact us',
        'is_public' => true,
        'expires_at' => $expiresAt,
        'groups' => [
            [
                'key' => 'details',
                'name' => 'Your details',
                'order' => 1,
                'fields' => [
                    ['key' => 'name', 'name' => 'Name', 'validations' => ['required']],
                ],
            ],
        ],
    ]);

    expect($form)->toBeInstanceOf(FormDefinitionData::class)
        ->and($form->isPublic)->toBeTrue()
        ->and($form->expiresAt)->toBe($expiresAt)
        ->and($form->groups)->toHaveCount(1);

    $group = $form->groups[0];
    expect($group)->toBeInstanceOf(GroupDefinitionData::class)
        ->and($group->order)->toBe(1)
        ->and($group->fields)->toHaveCount(1);

    expect($group->fields[0])->toBeInstanceOf(FieldDefinitionData::class)
        ->and($group->fields[0]->validations)->toBe(['required']);
});

it('reads camelCase keys from a form array', function () {
    $form = FormDefinitionData::fromArray([
        'key' => 'a',
        'name' => 'A',
        'isPublic' => true,
    ]);

    expect($form->isPublic)->toBeTrue()
        ->and($form->groups)->toBe([]);
});
