<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Forms\Actions\StoreSubmissionAction;
use RoundlyConsulting\Forms\Actions\ValidateFieldTypesAction;
use RoundlyConsulting\Forms\Exceptions\InvalidFieldValueException;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Submissions\SubmissionQuery;

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

it('falls back to string for an unmapped type via a bad config value', function () {
    config()->set('forms.field_types.weird', 'not-a-type');

    expect(typedField('weird')->attributeType())->toBe(AttributeType::String_);
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
        ->toThrow(InvalidFieldValueException::class);
});

it('accepts a valid value and ignores null and hidden fields', function () {
    $field = typedField('number');
    $form = $field->form->fresh()->load('fields');

    app(ValidateFieldTypesAction::class)->execute($form, requestFor($field, '99'));

    // A null value is skipped entirely rather than raising.
    app(ValidateFieldTypesAction::class)->execute($form, Request::create('t'));
})->throwsNoExceptions();
