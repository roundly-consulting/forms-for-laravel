<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Testing;

use RoundlyConsulting\Forms\Models\Form;

/**
 * Registers Pest expectation matchers for form assertions. Host applications
 * call {@see FormExpectations::register()} from their tests/Pest.php.
 *
 * The matchers are only defined when Pest's expectation API is available, so
 * this file never pulls Pest into the package's runtime and static analysis
 * stays clean.
 */
final class FormExpectations
{
    public static function register(): void
    {
        if (! function_exists('expect')) {
            return;
        }

        expect()->extend('toBeAcceptingSubmissions', function (): mixed {
            /** @var Form $form */
            $form = $this->value;

            expect($form->isAcceptingSubmissions())->toBeTrue();

            return $this;
        });

        expect()->extend('toBeExpired', function (): mixed {
            /** @var Form $form */
            $form = $this->value;

            expect($form->isExpired())->toBeTrue();

            return $this;
        });
    }
}
