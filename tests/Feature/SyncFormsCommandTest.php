<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;

/** @return array<string, mixed> */
function syncDefinition(string $nameField = 'Name'): array
{
    return [
        'key' => 'contact',
        'name' => 'Contact',
        'is_public' => true,
        'groups' => [
            [
                'key' => 'details',
                'name' => 'Details',
                'fields' => [
                    ['key' => 'name', 'name' => $nameField, 'rules' => ['required']],
                ],
            ],
        ],
    ];
}

it('creates configured forms via forms:sync', function () {
    config()->set('forms.definitions', [syncDefinition()]);

    $exit = Artisan::call('forms:sync');

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('Synced form [contact].')
        ->and(Form::query()->where('key', 'contact')->exists())->toBeTrue()
        ->and(Field::query()->where('key', 'name')->sole()->validations)->toBe(['required']);
});

it('is idempotent and updates changed forms', function () {
    config()->set('forms.definitions', [syncDefinition('Name')]);
    Artisan::call('forms:sync');

    config()->set('forms.definitions', [syncDefinition('Full name')]);
    Artisan::call('forms:sync');

    expect(Form::query()->count())->toBe(1)
        ->and(Group::query()->count())->toBe(1)
        ->and(Field::query()->count())->toBe(1)
        ->and(Field::query()->where('key', 'name')->sole()->name)->toBe('Full name');
});

it('reports when no definitions are configured', function () {
    config()->set('forms.definitions', []);

    $exit = Artisan::call('forms:sync');

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('No form definitions configured.');
});
