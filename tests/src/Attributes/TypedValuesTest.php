<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Forms\Actions\StoreSubmissionAction;
use RoundlyConsulting\Forms\Actions\ValidateFieldTypesAction;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\GroupBuilder;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Submissions\SubmissionQuery;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

function typedField(string $type): Field
{
    $slug = Str::random(8);
    $form = Form::factory()->public()->create(['key' => "form-{$slug}"]);
    $group = Group::factory()->for($form)->create(['key' => 'main']);

    return Field::factory()->for($form)->for($group)->create(['key' => 'answer', 'type' => $type]);
}

function storeValue(Field $field, mixed $value): Submission
{
    return Submission::factory()->create([
        'field_id' => $field->getKey(),
        'form_id' => $field->form_id,
        'group_id' => $field->group_id,
        'value' => ['value' => $value],
    ]);
}

/** Build a request payload addressing the field by its dotted form path. */
function requestFor(Field $field, mixed $value): Request
{
    $payload = [];
    data_set($payload, $field->path(), $value);

    return Request::create('t', parameters: $payload);
}

it('maps field types to attribute types', function () {
    expect(typedField('number')->attributeType())->toBe(AttributeType::Integer)
        ->and(typedField('checkbox')->attributeType())->toBe(AttributeType::Boolean)
        ->and(typedField('date')->attributeType())->toBe(AttributeType::DateTime)
        ->and(typedField('multiselect')->attributeType())->toBe(AttributeType::Array_)
        ->and(typedField('text')->attributeType())->toBe(AttributeType::String_);
});

it('refuses a field type mapped to an unknown attribute type (strict config)', function () {
    config()->set('forms.field_types.weird', 'not-a-type');

    expect(fn () => typedField('weird')->attributeType())->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [forms.field_types.weird] must be one of [string, integer, float, boolean, array, datetime], [not-a-type] given.',
    );
});

it('refuses a field type map that is not an array (strict config)', function () {
    config()->set('forms.field_types', 'number:integer');

    expect(fn () => typedField('number')->attributeType())->toThrow(InvalidConfigurationException::class, 'forms.field_types');
});

it('reads an unmapped field type as a string (strict config)', function () {
    config()->set('forms.field_types', null);

    expect(typedField('number')->attributeType())->toBe(AttributeType::String_);
});

it('casts a stored number value back to an int', function () {
    expect(storeValue(typedField('number'), '42')->typedValue())->toBe(42);
});

it('casts a stored checkbox value back to a bool', function () {
    expect(storeValue(typedField('checkbox'), '1')->typedValue())->toBeTrue();
});

it('casts a stored date value back to an immutable Carbon', function () {
    $submission = storeValue(typedField('date'), '2024-01-15');

    expect($submission->typedValue())->toBeInstanceOf(CarbonImmutable::class)
        ->and($submission->typedValue()->format('Y-m-d'))->toBe('2024-01-15');
});

it('casts an already-array value for a multiselect field', function () {
    expect(storeValue(typedField('multiselect'), ['a', 'b'])->typedValue())->toBe(['a', 'b']);
});

it('passes an unmapped type through as the raw string', function () {
    expect(storeValue(typedField('text'), 'hello')->typedValue())->toBe('hello');
});

it('returns null for a null stored value', function () {
    expect(storeValue(typedField('number'), null)->typedValue())->toBeNull();
});

it('returns typed values through the submission query', function () {
    $field = typedField('number');
    $form = $field->form->fresh();

    app(StoreSubmissionAction::class)->execute($form, requestFor($field, '7'));

    $assembled = (new SubmissionQuery($form))->first();

    expect($assembled?->value('answer'))->toBe(7);
});

it('rejects a value that does not satisfy the mapped type', function () {
    $field = typedField('number');
    $form = $field->form->fresh()->load('fields');

    expect(fn () => app(ValidateFieldTypesAction::class)->execute($form, requestFor($field, 'not-a-number')))
        ->toThrow(ValidationException::class, 'field must be a valid integer.');
});

it('accepts a valid value and ignores null and hidden fields', function () {
    $field = typedField('number');
    $form = $field->form->fresh()->load('fields');

    app(ValidateFieldTypesAction::class)->execute($form, requestFor($field, '99'));

    // A null value is skipped entirely rather than raising.
    app(ValidateFieldTypesAction::class)->execute($form, Request::create('t'));
})->throwsNoExceptions();

/*
 * Review fixes (2026-09-28) — one bad stored value must not break reading a whole form's
 * submissions. A hidden conditional field skips validation, so its raw input is no longer
 * stored; and a typed read that cannot convert a value hands back that value as stored
 * instead of throwing.
 */

it('does not store the input of a field its conditions hide', function () {
    $form = Forms::define('kyc', 'KYC')
        ->public()
        ->group('main', 'Main', function (GroupBuilder $g): void {
            $g->field('adult', 'Adult');
            $g->field('born', 'Born')->date()->visibleWhen('adult', 'yes');
        })
        ->create();

    $request = Request::create('t', 'POST', ['kyc' => ['main' => ['adult' => 'no', 'born' => 'garbage']]]);

    expect(Forms::validate($form, $request))->toBe([]);

    Forms::submit($form, $request);

    expect(Forms::submissions($form)->first()?->values)->toBe(['adult' => 'no', 'born' => null]);
});

it('degrades a typed read per value instead of failing the whole reader', function (string $type, mixed $stored) {
    $field = typedField($type);
    $form = $field->form->fresh();
    $name = Field::factory()->for($form)->for($field->group)->create(['key' => 'name', 'type' => 'text']);
    $uuid = Str::orderedUuid()->toString();

    Forms::createSubmission($field, ['value' => $stored], uuid: $uuid);
    Forms::createSubmission($name, ['value' => 'Ann'], uuid: $uuid);

    expect(Forms::submissions($form)->first()?->values)->toEqual(['answer' => $stored, 'name' => 'Ann']);
})->with([
    'date' => ['date', 'garbage'],
    'number' => ['number', 'forty-two'],
    'decimal number' => ['number', '3.5'],
    'float' => ['float', 'n/a'],
    'checkbox' => ['checkbox', 'maybe'],
    'multiselect' => ['multiselect', '{not json'],
]);

/*
 * Review fixes (2026-09-28) — a value that fails its field's type check is the sender's
 * mistake, so it is a validation error (422) keyed by the field's path, not a 500. A
 * `number()` field is whole numbers (it maps to `integer`), so a decimal is one of them.
 */

it('rejects a decimal in a number() field as a validation error', function () {
    $form = Forms::define('order', 'Order')
        ->public()
        ->group('main', 'Main', fn (GroupBuilder $g) => $g->field('qty', 'Quantity')->number())
        ->create();

    $validate = fn (string $qty) => Forms::validate($form, Request::create('t', 'POST', ['order' => ['main' => ['qty' => $qty]]]));

    expect($validate('3'))->toBe(['order' => ['main' => ['qty' => '3']]]);

    try {
        $validate('3.5');
        $this->fail('A decimal passed a number() field.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['order.main.qty' => ['The Quantity field must be a valid integer.']]);
    }
});

it('reports every field that fails its type check at once', function () {
    $form = Forms::define('mixed', 'Mixed')
        ->public()
        ->group('main', 'Main', function (GroupBuilder $g): void {
            $g->field('qty', 'Quantity')->type('number');
            $g->field('terms', 'Terms')->type('checkbox');
            $g->field('note', 'Note');
        })
        ->create();

    expect(fn () => Forms::validate($form, Request::create('t', 'POST', ['mixed' => ['main' => [
        'qty' => 'many', 'terms' => 'maybe', 'note' => 'free text',
    ]]])))->toThrow(function (ValidationException $exception): void {
        expect(array_keys($exception->errors()))->toBe(['mixed.main.qty', 'mixed.main.terms']);
    });
});

/*
 * Review fixes (2026-09-28) — a `time` field is a time of day, not a moment: it reads back
 * exactly as stored instead of picking up the date of the day it is read on.
 */

it('reads a time field back as stored, whatever the day it is read on', function () {
    $field = typedField('time');
    storeValue($field, '14:30');

    $read = function () use ($field): mixed {
        return Submission::query()->where('field_id', $field->getKey())->sole()->typedValue();
    };

    CarbonImmutable::setTestNow('2031-05-05 09:00:00');
    $first = $read();
    CarbonImmutable::setTestNow('2031-05-06 09:00:00');
    $second = $read();
    CarbonImmutable::setTestNow();

    expect($field->attributeType())->toBe(AttributeType::String_)
        ->and($first)->toBe('14:30')
        ->and($second)->toBe('14:30');
});
