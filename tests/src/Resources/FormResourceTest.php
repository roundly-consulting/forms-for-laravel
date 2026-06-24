<?php

declare(strict_types=1);

use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Resources\FormResource;

it('returns correct structure of resource', function () {
    $form = Form::factory()->create(['is_public' => false, 'expires_at' => '2023-04-17 13:05:00']);
    $group = Group::factory()->for($form)->create();

    $field = Field::factory()
        ->for($form)
        ->for($group)
        ->create();

    $resource = FormResource::make($form)->resolve();

    expect($resource['name'])->toBe($form->name)
        ->and($resource['key'])->toBe($form->key)
        ->and($resource['expires_at'])->toBe('2023-04-17 13:05:00')
        ->and($resource['is_public'])->toBe(false);
});
