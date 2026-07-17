<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\GroupBuilder;
use RoundlyConsulting\Forms\Tests\testable\CustomField;
use RoundlyConsulting\Forms\Tests\testable\CustomForm;
use RoundlyConsulting\Forms\Tests\testable\CustomFormSubmission;
use RoundlyConsulting\Forms\Tests\testable\CustomGroup;
use RoundlyConsulting\Forms\Tests\testable\CustomSubmission;

/**
 * The model-swap proofs (S) for all five `forms.models.*` seams, driven through the REAL
 * flows rather than through the resolver.
 *
 * The bugs this class of test exists for are the retrofit's single biggest class:
 *
 *  - a runtime `config()->set()` leaves every listener the provider hung at boot on the
 *    packaged class (media #28);
 *  - `instanceof` passes for a row created as the *packaged* class — which never fires the
 *    host's model events (permissions #31). Only a `created` event counted on the subclass
 *    itself proves the row was made as the host's model, which is why each fixture uses
 *    {@see CountsCreations}: without it `toHonourModelSwap` silently downgrades;
 *  - a hard-coded call site sitting beside an honoured config (shops #3, media #28) — which
 *    is why the exercises below go through the facade and the builders, never through the
 *    seam. Reading back with `FormModel::class()::query()` would only prove the resolver
 *    resolves.
 *
 * The swap is applied before boot by {@see SwappedModelsTestCase}, which this directory is
 * bound to — Pest binds a test case per directory, not per file.
 */
function defineSwapForm(string $key = 'apply'): void
{
    Forms::define($key, 'Application')
        ->public()
        ->group('g', 'G', function (GroupBuilder $g): void {
            $g->field('name', 'Name')->rules(['required']);
        })
        ->create();
}

it('honours a host form model through the real define flow', function (): void {
    expect('forms.models.form')->toHonourModelSwap(CustomForm::class, function (): array {
        defineSwapForm();

        return [Forms::find('apply')];
    });
});

it('honours a host group model through the real define flow', function (): void {
    expect('forms.models.group')->toHonourModelSwap(CustomGroup::class, function (): array {
        defineSwapForm();

        return Forms::find('apply')->groups()->get()->all();
    });
});

it('honours a host field model through the real define flow', function (): void {
    expect('forms.models.field')->toHonourModelSwap(CustomField::class, function (): array {
        defineSwapForm();

        return Forms::find('apply')->groups()->first()->fields()->get()->all();
    });
});

it('honours a host submission model through the real submit flow', function (): void {
    expect('forms.models.submission')->toHonourModelSwap(CustomSubmission::class, function (): array {
        defineSwapForm();
        $form = Forms::find('apply');

        Forms::submit($form, Request::create('t', parameters: [
            'apply' => ['g' => ['name' => 'Jane']],
        ]));

        // $form->submissions() is the real relation; Forms::submissions() assembles DTOs
        // rather than returning models, so it cannot answer this question.
        return $form->submissions()->get()->all();
    });
});

it('honours a host aggregate model through the real submit flow', function (): void {
    expect('forms.models.form_submission')->toHonourModelSwap(CustomFormSubmission::class, function (): array {
        defineSwapForm();
        $form = Forms::find('apply');

        Forms::submit($form, Request::create('t', parameters: [
            'apply' => ['g' => ['name' => 'Jane']],
        ]));

        // The aggregate is reached from the per-field submission rows it groups.
        return $form->submissions()->get()
            ->map(fn ($submission) => $submission->formSubmission)
            ->filter()
            ->all();
    });
});

// The structural half of each seam — the models are non-final, and every `forms.models.*`
// key really defaults to the packaged model — is pinned once in tests/ArchTest.php by
// `ArchPresets::swappableModelsAreNotFinal()`. It deliberately does NOT live here: that
// preset asserts the config *default*, which this directory has swapped away.
