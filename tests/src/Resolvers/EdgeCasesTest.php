<?php

declare(strict_types=1);

use RoundlyConsulting\Forms\Exceptions\UnresolvableFieldException;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Resolvers\DefaultResolver;

it('throws when no resolver can be resolved for the field type', function () {
    config()->set('forms.fields', []);

    $field = Field::factory()->make(['type' => 'mystery']);

    expect(fn () => $field->resolver())->toThrow(UnresolvableFieldException::class);
});

it('returns the autofill class string when it is not an autofill implementation', function () {
    $field = Field::factory()->make(['autofill' => stdClass::class]);

    expect($field->getAutofillValue())->toBe(stdClass::class);
});

it('returns null autofill when blank', function () {
    $field = Field::factory()->make(['autofill' => null]);

    expect($field->getAutofillValue())->toBeNull();
});

it('returns null from storage when no submission exists', function () {
    $form = Form::factory()->create(['key' => 'profile']);
    $group = Group::factory()->for($form)->create(['key' => 'details']);
    $field = Field::factory()->for($form)->for($group)->create(['key' => 'name']);

    expect((new DefaultResolver($field))->fromStorage())->toBeNull();
});

it('returns null from storage when the sender filter matches nothing', function () {
    $form = Form::factory()->create(['key' => 'profile']);
    $group = Group::factory()->for($form)->create(['key' => 'details']);
    $field = Field::factory()->for($form)->for($group)->create(['key' => 'name']);

    $otherGroup = Group::factory()->for($form)->create(['key' => 'other']);

    expect((new DefaultResolver($field))->fromStorage($otherGroup))->toBeNull();
});
