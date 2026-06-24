<?php

declare(strict_types=1);

use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Resources\FieldResource;

it('returns correct structure of resource', function () {
    $form = Form::factory()->create(['key' => 'profile']);
    $group = Group::factory()->for($form)->create(['key' => 'user-details']);

    $field = Field::factory()
        ->for($form)
        ->for($group)
        ->create();

    $resource = FieldResource::make($field)->resolve();

    expect($resource['name'])->toBe($field->name)
        ->and($resource['key'])->toBe($field->key)
        ->and($resource['order'])->toBe($field->order)
        ->and($resource['help'])->toBe($field->help)
        ->and($resource['type'])->toBe($field->type)
        ->and($resource['autofill'])->toBe([
            'enabled' => false,
            'value' => null,
        ])
        ->and($resource['options'])->toBe([
            'has_options' => false,
            'values' => null,
        ])
        ->and($resource['validations'])->toBe([
            'has_validations' => false,
            'validations' => null,
        ]);
});
