<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RoundlyConsulting\Forms\Actions\ValidateSubmissionAction;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\GroupBuilder;
use RoundlyConsulting\Forms\Models\Field;

it('stores and applies per-field custom validation messages', function () {
    Forms::define('contact', 'Contact')
        ->public()
        ->group('g', 'G', function (GroupBuilder $g): void {
            $g->field('email', 'Email')->rules(['required', 'email'], [
                'required' => 'We really need your email.',
            ]);
        })
        ->create();

    $field = Field::query()->where('key', 'email')->sole();
    expect($field->messages)->toBe(['required' => 'We really need your email.']);

    $message = null;

    try {
        app(ValidateSubmissionAction::class)->execute(
            Forms::find('contact'),
            Request::create('t', parameters: ['contact' => ['g' => []]]),
        );
    } catch (ValidationException $e) {
        $message = $e->validator->errors()->first('contact.g.email');
    }

    expect($message)->toBe('We really need your email.');
});

it('supports a standalone messages() call on the builder', function () {
    Forms::define('c', 'C')
        ->group('g', 'G', function (GroupBuilder $g): void {
            $g->field('name', 'Name')->rules(['required'])->messages(['required' => 'Name please.']);
        })
        ->create();

    expect(Field::query()->where('key', 'name')->sole()->messages)
        ->toBe(['required' => 'Name please.']);
});
