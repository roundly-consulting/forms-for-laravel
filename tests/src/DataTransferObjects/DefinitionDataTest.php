<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Forms\DataTransferObjects\FieldDefinitionData;
use RoundlyConsulting\Forms\DataTransferObjects\FormDefinitionData;
use RoundlyConsulting\Forms\DataTransferObjects\GroupDefinitionData;
use RoundlyConsulting\Forms\Events\FormUpdated;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\Models\Form;

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

/*
 * Review fixes (2026-09-28) — a definition read from config carries `expires_at` as a
 * string (or a timestamp), not a Carbon instance; it is parsed instead of throwing a
 * TypeError from `forms:sync`.
 */

it('parses a string or timestamp expires_at from a definition array', function (mixed $given) {
    $form = FormDefinitionData::fromArray(['key' => 'a', 'name' => 'A', 'expires_at' => $given]);

    expect($form->expiresAt?->equalTo(Carbon::parse('2031-01-01 00:00:00')))->toBeTrue();
})->with([
    'string' => '2031-01-01 00:00:00',
    'date-only string' => '2031-01-01',
    'timestamp' => fn (): int => Carbon::parse('2031-01-01 00:00:00')->getTimestamp(),
    'DateTimeImmutable' => fn (): DateTimeImmutable => new DateTimeImmutable('2031-01-01 00:00:00'),
]);

it('reads an empty expires_at as no expiry', function (mixed $given) {
    expect(FormDefinitionData::fromArray(['key' => 'a', 'name' => 'A', 'expiresAt' => $given])->expiresAt)->toBeNull();
})->with([null, '']);

it('syncs a definition whose expires_at is a string, idempotently', function () {
    Event::fake([FormUpdated::class]);

    $definition = ['key' => 'promo', 'name' => 'Promo', 'is_public' => true, 'expires_at' => '2031-01-01 00:00:00'];

    expect(Forms::sync([$definition]))->toBe(['promo'])
        ->and(Form::query()->forKey('promo')->sole()->expires_at?->equalTo(Carbon::parse('2031-01-01 00:00:00')))->toBeTrue();

    Forms::sync([$definition]);

    Event::assertNotDispatched(FormUpdated::class);
});

it('refuses an expires_at that is not a date at all', function () {
    FormDefinitionData::fromArray(['key' => 'a', 'name' => 'A', 'expires_at' => ['2031-01-01']]);
})->throws(InvalidArgumentException::class, 'expires_at must be a date string, a timestamp or a date, array given');
