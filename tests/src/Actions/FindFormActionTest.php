<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Forms\Actions\FindFormAction;
use RoundlyConsulting\Forms\Exceptions\FormNotFoundException;
use RoundlyConsulting\Forms\Exceptions\FormsException;
use RoundlyConsulting\Forms\Exceptions\MultipleFormsFoundException;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;

it('finds a form with ordered groups and fields', function () {
    $form = Form::factory()->create(['key' => 'profile']);
    $group = Group::factory()->for($form)->create(['key' => 'details', 'order' => 1]);
    $other = Group::factory()->for($form)->create(['key' => 'extra', 'order' => 0]);
    Field::factory()->for($form)->for($group)->create(['key' => 'b', 'order' => 1]);
    Field::factory()->for($form)->for($group)->create(['key' => 'a', 'order' => 0]);

    $found = app(FindFormAction::class)->execute('profile');

    expect($found->relationLoaded('groups'))->toBeTrue()
        ->and($found->groups->first()?->key)->toBe('extra')
        ->and($found->groups->firstWhere('key', 'details')?->fields->first()?->key)->toBe('a');

    unset($other);
});

it('throws a form-not-found exception for an unknown key', function () {
    expect(fn () => app(FindFormAction::class)->execute('missing'))
        ->toThrow(FormNotFoundException::class);

    expect(new FormNotFoundException)->toBeInstanceOf(FormsException::class);
});

it('throws a multiple-forms-found exception for duplicate keys', function () {
    // The unique index normally prevents this; drop it to simulate corrupt data.
    Schema::table('forms', function ($table): void {
        $table->dropUnique(['key']);
    });

    Form::factory()->create(['key' => 'dupe']);
    Form::factory()->create(['key' => 'dupe']);

    expect(fn () => app(FindFormAction::class)->execute('dupe'))
        ->toThrow(MultipleFormsFoundException::class);
});
