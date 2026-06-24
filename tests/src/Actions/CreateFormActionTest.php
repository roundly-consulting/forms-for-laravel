<?php

declare(strict_types=1);

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Forms\Actions\CreateFormAction;
use RoundlyConsulting\Forms\DataTransferObjects\FieldDefinitionData;
use RoundlyConsulting\Forms\DataTransferObjects\FormDefinitionData;
use RoundlyConsulting\Forms\DataTransferObjects\GroupDefinitionData;
use RoundlyConsulting\Forms\Events\FormCreated;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;

it('creates a flat form', function () {
    $expiresAt = Carbon::parse('2030-01-01 00:00:00');

    $form = app(CreateFormAction::class)->execute(new FormDefinitionData(
        key: 'contact',
        name: 'Contact us',
        expiresAt: $expiresAt,
        isPublic: true,
    ));

    expect($form)->toBeInstanceOf(Form::class)
        ->and($form->is_public)->toBeTrue()
        ->and($form->expires_at->equalTo($expiresAt))->toBeTrue()
        ->and(Form::query()->where('key', 'contact')->exists())->toBeTrue();
});

it('creates a nested form with groups and fields in one call', function () {
    $form = app(CreateFormAction::class)->execute(new FormDefinitionData(
        key: 'contact',
        name: 'Contact us',
        groups: [
            new GroupDefinitionData('details', 'Your details', fields: [
                new FieldDefinitionData('name', 'Name', validations: ['required']),
                new FieldDefinitionData('email', 'Email', type: 'email'),
            ]),
        ],
    ));

    expect(Group::query()->where('form_id', $form->getKey())->count())->toBe(1)
        ->and(Field::query()->where('form_id', $form->getKey())->count())->toBe(2);

    $field = Field::query()->where('key', 'name')->sole();
    expect($field->validations)->toBe(['required'])
        ->and($field->order)->toBe(0);

    expect(Field::query()->where('key', 'email')->sole()->order)->toBe(1);
});

it('dispatches a form-created event', function () {
    Event::fake();

    app(CreateFormAction::class)->execute(new FormDefinitionData('contact', 'Contact us'));

    Event::assertDispatched(FormCreated::class);
});

it('rolls back the whole structure when a field fails', function () {
    expect(fn () => app(CreateFormAction::class)->execute(new FormDefinitionData(
        key: 'contact',
        name: 'Contact us',
        groups: [
            new GroupDefinitionData('details', 'Your details', fields: [
                new FieldDefinitionData('name', 'Name'),
                // Duplicate key within the group violates the unique index mid-loop.
                new FieldDefinitionData('name', 'Name again'),
            ]),
        ],
    )))->toThrow(UniqueConstraintViolationException::class);

    expect(Form::query()->count())->toBe(0)
        ->and(Group::query()->count())->toBe(0)
        ->and(Field::query()->count())->toBe(0);
});
