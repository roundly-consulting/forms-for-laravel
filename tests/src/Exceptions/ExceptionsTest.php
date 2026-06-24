<?php

declare(strict_types=1);

use RoundlyConsulting\Forms\Exceptions\FormNotFoundException;
use RoundlyConsulting\Forms\Exceptions\FormsException;
use RoundlyConsulting\Forms\Exceptions\MultipleFormsFoundException;
use RoundlyConsulting\Forms\Exceptions\UnresolvableFieldException;

it('builds a form-not-found exception from a translated message', function () {
    $e = FormNotFoundException::forKey('contact');

    expect($e)->toBeInstanceOf(FormsException::class)
        ->and($e->getMessage())->toContain('contact');
});

it('builds a multiple-forms-found exception', function () {
    $e = MultipleFormsFoundException::forKey('contact');

    expect($e)->toBeInstanceOf(FormsException::class)
        ->and($e->getMessage())->toContain('contact');
});

it('builds an unresolvable-field exception', function () {
    $e = UnresolvableFieldException::forType('date');

    expect($e)->toBeInstanceOf(FormsException::class)
        ->and($e->getMessage())->toContain('date');
});

it('reflects an overridden translation', function () {
    app('translator')->addLines([
        'messages.form_not_found' => 'Custom: :key',
    ], 'en', 'forms');

    expect(FormNotFoundException::forKey('x')->getMessage())->toBe('Custom: x');
});
