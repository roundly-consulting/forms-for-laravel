<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Testing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionResult;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\Models\Form;

/**
 * Opt-in testing ergonomics for host applications. Use it from a Pest/PHPUnit
 * test case:
 *
 *     uses(RoundlyConsulting\Forms\Testing\InteractsWithForms::class);
 *
 * It is intentionally framework-light and pulls in no runtime dependency on
 * Pest.
 */
trait InteractsWithForms
{
    /**
     * Swap the live Forms manager for a recording {@see FormsFake} and return it.
     */
    protected function fakeForms(): FormsFake
    {
        return Forms::fake();
    }

    /**
     * Submit a form with the given values, building the nested request payload
     * the resolvers expect (`form_key.group_key.field_key`).
     *
     * @param  array<string, mixed>  $values
     */
    protected function submitForm(Form $form, array $values, ?Model $sender = null): SubmissionResult
    {
        return Forms::submit($form, $this->formRequest($form, $values), $sender);
    }

    /**
     * Save a resumable draft for a form with the given values.
     *
     * @param  array<string, mixed>  $values
     */
    protected function draftForm(Form $form, array $values, ?Model $sender = null, ?string $uuid = null): SubmissionResult
    {
        return Forms::draft($form, $this->formRequest($form, $values), $sender, $uuid);
    }

    /**
     * @param  array<string, mixed>  $values  keyed by `group_key.field_key` or nested per group.
     */
    private function formRequest(Form $form, array $values): Request
    {
        return Request::create('testing', 'POST', [$form->key => $values]);
    }
}
