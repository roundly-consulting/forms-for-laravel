<?php

declare(strict_types=1);

use RoundlyConsulting\Forms\Exceptions\DraftNotFoundException;
use RoundlyConsulting\Forms\Exceptions\FormNotFoundException;
use RoundlyConsulting\Forms\Exceptions\FormsException;
use RoundlyConsulting\Forms\Exceptions\MultipleFormsFoundException;
use RoundlyConsulting\Forms\Exceptions\SubmissionNotFoundException;
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

it('names the uuid of a missing draft or submission in the current locale', function () {
    expect(DraftNotFoundException::forUuid('abc')->getMessage())->toBe('No draft submission found for UUID [abc].')
        ->and(SubmissionNotFoundException::forUuid('abc')->getMessage())->toBe('No submission found for UUID [abc].');

    app()->setLocale('sk');

    expect(DraftNotFoundException::forUuid('abc')->getMessage())->toBe('Koncept odpovede s UUID [abc] sa nenašiel.')
        ->and(SubmissionNotFoundException::forUuid('abc')->getMessage())->toBe('Odpoveď s UUID [abc] sa nenašla.');
});
