<?php

declare(strict_types=1);

use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;

it('syncs forms from an explicit definition list', function () {
    $synced = Forms::sync([
        [
            'key' => 'lead',
            'name' => 'Lead',
            'is_public' => true,
            'groups' => [
                ['key' => 'g', 'name' => 'G', 'fields' => [
                    ['key' => 'email', 'name' => 'Email', 'rules' => ['required', 'email']],
                ]],
            ],
        ],
    ]);

    expect($synced)->toBe(['lead'])
        ->and(Form::query()->where('key', 'lead')->exists())->toBeTrue()
        ->and(Field::query()->where('key', 'email')->sole()->validations)->toBe(['required', 'email']);
});

it('updates an existing form on a second sync', function () {
    Forms::sync([['key' => 'lead', 'name' => 'Lead', 'groups' => []]]);
    Forms::sync([['key' => 'lead', 'name' => 'New name', 'groups' => []]]);

    expect(Form::query()->where('key', 'lead')->sole()->name)->toBe('New name')
        ->and(Form::query()->count())->toBe(1);
});
