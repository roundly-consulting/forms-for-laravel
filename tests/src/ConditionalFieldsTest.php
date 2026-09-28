<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RoundlyConsulting\Forms\Actions\ValidateSubmissionAction;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\GroupBuilder;
use RoundlyConsulting\Forms\Models\Field;

function conditionalForm(): void
{
    Forms::define('shipping', 'Shipping')
        ->public()
        ->group('addr', 'Address', function (GroupBuilder $g): void {
            $g->field('country', 'Country');
            $g->field('state', 'State')->requiredWhen('country', 'US');
        })
        ->create();
}

it('skips a conditionally-required field when its condition is not met', function () {
    conditionalForm();
    $form = Forms::find('shipping');

    // `country` has no rules and `state` is hidden, so nothing is enforced.
    $validated = app(ValidateSubmissionAction::class)->execute($form, Request::create('t', parameters: [
        'shipping' => ['addr' => ['country' => 'CZ']],
    ]));

    expect($validated)->toBe([]);
});

it('enforces a conditionally-required field when its condition is met', function () {
    conditionalForm();
    $form = Forms::find('shipping');

    expect(fn () => app(ValidateSubmissionAction::class)->execute($form, Request::create('t', parameters: [
        'shipping' => ['addr' => ['country' => 'US']],
    ])))->toThrow(ValidationException::class);
});

it('passes when the conditionally-required field is supplied', function () {
    conditionalForm();
    $form = Forms::find('shipping');

    $validated = app(ValidateSubmissionAction::class)->execute($form, Request::create('t', parameters: [
        'shipping' => ['addr' => ['country' => 'US', 'state' => 'CA']],
    ]));

    expect($validated['shipping']['addr']['state'])->toBe('CA');
});

/**
 * `toEqual` on the condition list: `conditions` is stored as `jsonb`, and while `jsonb`
 * preserves the order of ARRAY elements (so the list itself is still pinned), it sorts the
 * keys of each OBJECT inside it — `field`, `value`, `operator` by (length, bytes). Each
 * condition is read by key (`$condition['field']`), never by position, so key order is not
 * the contract. `isVisible()` below is what actually pins the semantics.
 */
it('stores conditions on the field and exposes visibility', function () {
    conditionalForm();
    $state = Field::query()->where('key', 'state')->sole();

    expect($state->conditions)->toEqual([
        ['field' => 'country', 'operator' => '=', 'value' => 'US'],
    ])
        ->and($state->isVisible(['shipping' => ['addr' => ['country' => 'US']]]))->toBeTrue()
        ->and($state->isVisible(['shipping' => ['addr' => ['country' => 'CZ']]]))->toBeFalse()
        ->and($state->isVisible([]))->toBeFalse();
});

it('treats a field with no conditions as always visible', function () {
    conditionalForm();
    $country = Field::query()->where('key', 'country')->sole();

    expect($country->isVisible([]))->toBeTrue();
});

it('evaluates each condition operator', function () {
    $field = Field::factory()->make();

    $matches = fn (string $op, mixed $expected, mixed $actual): bool => (function () use ($field, $op, $expected, $actual): bool {
        $reflection = new ReflectionMethod($field, 'matchesCondition');

        return (bool) $reflection->invoke($field, $actual, ['operator' => $op, 'value' => $expected]);
    })();

    expect($matches('=', 'a', 'a'))->toBeTrue()
        ->and($matches('!=', 'a', 'b'))->toBeTrue()
        ->and($matches('!=', 'a', 'a'))->toBeFalse()
        ->and($matches('in', ['a', 'b'], 'a'))->toBeTrue()
        ->and($matches('in', 'notarray', 'a'))->toBeFalse()
        ->and($matches('not_in', ['a'], 'b'))->toBeTrue()
        ->and($matches('filled', null, 'x'))->toBeTrue()
        ->and($matches('empty', null, null))->toBeTrue();
});

it('ignores malformed condition entries', function () {
    $field = Field::factory()->make(['conditions' => ['not-an-array', ['no-field-key' => 1]]]);

    expect($field->isVisible([]))->toBeTrue();
});

it('supports visibleWhen with operators', function () {
    Forms::define('quiz', 'Quiz')
        ->public()
        ->group('g', 'G', function (GroupBuilder $g): void {
            $g->field('age', 'Age');
            $g->field('guardian', 'Guardian')->visibleWhen('age', [18, 21], 'in');
        })
        ->create();

    $guardian = Field::query()->where('key', 'guardian')->sole();

    expect($guardian->isVisible(['quiz' => ['g' => ['age' => 18]]]))->toBeTrue()
        ->and($guardian->isVisible(['quiz' => ['g' => ['age' => 30]]]))->toBeFalse();
});

/*
 * Review fixes (2026-09-28) — a condition may reference a field in another group (step) of
 * the same form. It used to build the path from the dependent field's own group, so a
 * cross-step condition never matched: the field stayed hidden and its `required` was skipped.
 */

function multiStepForm(): void
{
    Forms::define('signup', 'Signup')
        ->public()
        ->group('step1', 'Where', function (GroupBuilder $g): void {
            $g->field('country', 'Country');
        })
        ->group('step2', 'Details', function (GroupBuilder $g): void {
            $g->field('state', 'State')->requiredWhen('country', 'US');
            $g->field('zip', 'Zip')->requiredWhen('step1.country', 'US');
        })
        ->create();
}

it('enforces a condition on a field in another group of the same form', function () {
    multiStepForm();
    $form = Forms::find('signup');

    expect(fn () => Forms::validate($form, Request::create('t', parameters: [
        'signup' => ['step1' => ['country' => 'US']],
    ])))->toThrow(ValidationException::class, 'The State field is required');

    expect(Forms::validate($form, Request::create('t', parameters: [
        'signup' => ['step1' => ['country' => 'CZ']],
    ])))->toBe([]);
});

it('resolves an explicit group.field reference across groups', function () {
    multiStepForm();
    $zip = Field::query()->where('key', 'zip')->sole();

    expect($zip->isVisible(['signup' => ['step1' => ['country' => 'US']]]))->toBeTrue()
        ->and($zip->isVisible(['signup' => ['step1' => ['country' => 'CZ']]]))->toBeFalse();
});

it('prefers the dependent field\'s own group when two groups share the referenced key', function () {
    Forms::define('family', 'Family')
        ->public()
        ->group('applicant', 'Applicant', function (GroupBuilder $g): void {
            $g->field('minor', 'Minor');
        })
        ->group('guardian', 'Guardian', function (GroupBuilder $g): void {
            $g->field('minor', 'Minor');
            $g->field('consent', 'Consent')->visibleWhen('minor', 'yes');
        })
        ->create();

    $consent = Field::query()->where('key', 'consent')->sole();

    expect($consent->isVisible(['family' => ['guardian' => ['minor' => 'yes'], 'applicant' => ['minor' => 'no']]]))->toBeTrue()
        ->and($consent->isVisible(['family' => ['guardian' => ['minor' => 'no'], 'applicant' => ['minor' => 'yes']]]))->toBeFalse();
});
