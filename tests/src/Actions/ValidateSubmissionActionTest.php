<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RoundlyConsulting\Forms\Actions\FindFormAction;
use RoundlyConsulting\Forms\Actions\ValidateSubmissionAction;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;

beforeEach(function () {
    $form = Form::factory()->create(['key' => 'myform']);
    $group = Group::factory()->for($form)->create(['key' => 'mygroup']);
    Field::factory()->for($form)->for($group)->create([
        'key' => 'myfield',
        'name' => 'My Field',
        'validations' => ['required'],
    ]);
});

function validRequest(string $value): Request
{
    return Request::create('testing', parameters: [
        'myform' => ['mygroup' => ['myfield' => $value]],
    ]);
}

it('returns validated data for valid input', function () {
    $form = app(FindFormAction::class)->execute('myform');

    $validated = app(ValidateSubmissionAction::class)->execute($form, validRequest('Hello'));

    expect($validated)->toBe([
        'myform' => ['mygroup' => ['myfield' => 'Hello']],
    ]);
});

it('throws when input is invalid', function () {
    $form = app(FindFormAction::class)->execute('myform');

    app(ValidateSubmissionAction::class)->execute($form, validRequest(''));
})->throws(ValidationException::class);

it('uses the field name as the attribute label', function () {
    $form = app(FindFormAction::class)->execute('myform');

    $message = null;

    try {
        app(ValidateSubmissionAction::class)->execute($form, validRequest(''));
    } catch (ValidationException $e) {
        $message = $e->getMessage();
    }

    expect($message)->toContain('My Field');
});
