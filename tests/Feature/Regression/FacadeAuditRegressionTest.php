<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\GroupBuilder;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\FormSubmission;
use RoundlyConsulting\Forms\Tests\testable\Reviewer;

/*
 * Regressions from the 2026-09 facade audit. Each failed on the previous code: the fake
 * never overrode review(), so an opened review went unrecorded; `forms:sync` called its
 * action directly, bypassing the fake; and `define()->create()` ran the action the builder
 * held, so the fake saw the definition but never the created form.
 */

it('records a review opened under the fake', function (): void {
    config()->set('forms.approvals.enabled', true);

    if (! Schema::hasTable('reviewers')) {
        Schema::create('reviewers', function ($table): void {
            $table->id();
            $table->timestamps();
        });
    }

    $submission = FormSubmission::factory()->create();
    $reviewer = Reviewer::query()->create();
    $fake = Forms::fake();

    Forms::review($submission)->requiring([$reviewer])->open();

    $fake->assertReviewOpened();
});

it('records a forms:sync run under the fake', function (): void {
    config()->set('forms.definitions', [
        ['key' => 'lead', 'name' => 'Lead', 'groups' => []],
    ]);
    $fake = Forms::fake();

    $this->artisan('forms:sync')->assertSuccessful();

    $fake->assertSynced();
});

it('records the form a fluent definition creates', function (): void {
    $fake = Forms::fake();

    Forms::define('contact', 'Contact')
        ->group('g', 'G', fn (GroupBuilder $g) => $g->field('name', 'Name'))
        ->create();

    $fake->assertFormCreated(fn (Form $form): bool => $form->key === 'contact');
});
