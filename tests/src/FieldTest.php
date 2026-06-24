<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Resolvers\DefaultResolver;
use RoundlyConsulting\Forms\Tests\testable\CustomAutofill;
use RoundlyConsulting\Forms\Tests\testable\CustomResolver;

it('casts attributes', function () {
    $field = Field::factory()->make([
        'options' => [
            'CZ' => 'Czechia',
            'SK' => 'Slovakia',
        ],
        'validations' => [
            'required',
            'string',
            'in:CZ,SK',
        ],
        'order' => 4,
    ]);

    expect($field->options)->toBe([
        'CZ' => 'Czechia',
        'SK' => 'Slovakia',
    ]);
    expect($field->validations)->toBe([
        'required',
        'string',
        'in:CZ,SK',
    ]);
    expect($field->order)->toBe(4);
});

it('has method to check whether field has options', function () {
    $field = Field::factory()->make([
        'options' => [
            'CZ' => 'Czechia',
            'SK' => 'Slovakia',
        ],
    ]);

    $fieldWithoutOptions = Field::factory()->make([
        'options' => [],
    ]);

    expect($field->hasOptions())->toBeTrue()
        ->and($fieldWithoutOptions->hasOptions())->toBeFalse();
});

it('has method to check whether field has validations', function () {
    $field = Field::factory()->make([
        'validations' => [
            'required',
            'string',
            'in:CZ,SK',
        ],
    ]);

    $fieldWithoutValidations = Field::factory()->make([
        'options' => [],
    ]);

    expect($field->hasValidations())->toBeTrue()
        ->and($fieldWithoutValidations->hasValidations())->toBeFalse();
});

it('has relationship to form', function () {
    $field = Field::factory()->make();

    expect($field->form())->toBeInstanceOf(BelongsTo::class);
});

it('has relationship to group', function () {
    $field = Field::factory()->make();

    expect($field->group())->toBeInstanceOf(BelongsTo::class);
});

it('returns path of field generated from form and group', function () {
    $form = Form::factory()->create(['key' => 'profile']);
    $group = Group::factory()->for($form)->create(['key' => 'user-details']);

    $field = Field::factory()
        ->for($form)
        ->for($group)
        ->create(['key' => 'home-address']);

    expect($field->path())->toBe('profile.user-details.home-address');
});

it('returns default resolver of value', function () {
    $form = Form::factory()->create(['key' => 'profile']);
    $group = Group::factory()->for($form)->create(['key' => 'user-details']);

    $field = Field::factory()
        ->for($form)
        ->for($group)
        ->create(['key' => 'home-address']);

    expect($field->resolver())
        ->toBeInstanceOf(DefaultResolver::class);
});

it('returns default resolver of value when no resolver is registered in config', function () {
    $form = Form::factory()->create(['key' => 'profile']);
    $group = Group::factory()->for($form)->create(['key' => 'user-details']);

    $field = Field::factory()
        ->for($form)
        ->for($group)
        ->create([
            'key' => 'home-address',
            'type' => 'custom',
        ]);

    expect($field->resolver())
        ->toBeInstanceOf(DefaultResolver::class);
});

it('returns custom resolver of value resolved from config', function () {
    $form = Form::factory()->create(['key' => 'profile']);
    $group = Group::factory()->for($form)->create(['key' => 'user-details']);

    $field = Field::factory()
        ->for($form)
        ->for($group)
        ->create([
            'key' => 'home-address',
            'type' => 'custom',
        ]);

    config()->set('forms.fields.custom', CustomResolver::class);

    expect($field->resolver())
        ->toBeInstanceOf(CustomResolver::class);
});

it('returns autofill value directly from field definition', function () {
    $form = Form::factory()->create(['key' => 'profile']);
    $group = Group::factory()->for($form)->create(['key' => 'user-details']);

    $field = Field::factory()
        ->for($form)
        ->for($group)
        ->create([
            'key' => 'home-address',
            'autofill' => 'This is autofill',
        ]);

    expect($field->getAutofillValue())->toBe('This is autofill');
});

it('returns autofill value from custom class', function () {
    $form = Form::factory()->create(['key' => 'profile']);
    $group = Group::factory()->for($form)->create(['key' => 'user-details']);

    $field = Field::factory()
        ->for($form)
        ->for($group)
        ->create([
            'autofill' => CustomAutofill::class,
        ]);

    expect($field->getAutofillValue())->toBe("{$field->name} Autofill");
});
