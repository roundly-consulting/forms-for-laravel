<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Testing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
     * the resolvers expect (`form_key.group_key.field_key`). Values are nested per group
     * or flat by `group_key.field_key` (see {@see self::formRequest()}).
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
     * The request a form posts: `$values` nested per group (`['details' => ['name' => 'Ann']]`)
     * or flat by `group_key.field_key` (`['details.name' => 'Ann']`) — one style per group.
     *
     * @param  array<string, mixed>  $values
     */
    private function formRequest(Form $form, array $values): Request
    {
        return Request::create('testing', 'POST', [$form->key => Arr::undot($values)]);
    }
}
