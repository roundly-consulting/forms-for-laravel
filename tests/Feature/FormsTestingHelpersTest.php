<?php

declare(strict_types=1);

use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\GroupBuilder;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Testing\FormExpectations;
use RoundlyConsulting\Forms\Testing\FormsFake;

// Exercises Pest's dynamic test-case binding ($this + the InteractsWithForms
// trait on TestCase) and custom expectation matchers — features static analysis
// can't model, so this file is excluded from PHPStan (see phpstan.neon.dist).

function helperContactForm(): Form
{
    return Forms::define('contact', 'Contact us')
        ->public()
        ->group('details', 'Details', function (GroupBuilder $g): void {
            $g->field('name', 'Name');
        })
        ->create();
}

it('fakes, submits and drafts through the InteractsWithForms trait', function () {
    $fake = $this->fakeForms();

    expect($fake)->toBeInstanceOf(FormsFake::class);

    $form = helperContactForm();

    $this->submitForm($form, ['details' => ['name' => 'Ann']]);
    $fake->assertSubmitted($form);

    $draft = $this->draftForm($form, ['details' => ['name' => 'Bea']]);
    $fake->assertDrafted($form);
    expect($draft->uuid)->not->toBeEmpty();
});

it('exposes Pest expectation matchers for forms', function () {
    FormExpectations::register();
    FormExpectations::register(); // idempotent

    $accepting = helperContactForm();
    expect($accepting)->toBeAcceptingSubmissions();

    $expired = Form::factory()->create([
        'key' => 'old',
        'is_public' => true,
        'expires_at' => now()->subDay(),
    ]);
    expect($expired)->toBeExpired();
});

/*
 * Review fixes (2026-09-28) — the helpers take values nested per group or flat as
 * `group_key.field_key`, as their docblock says; flat keys were stored as null.
 */

it('submits flat group_key.field_key values as well as nested ones', function () {
    $form = Forms::define('signup', 'Sign up')
        ->public()
        ->group('details', 'Details', function (GroupBuilder $g): void {
            $g->field('name', 'Name');
            $g->field('email', 'Email');
        })
        ->group('extra', 'Extra', fn (GroupBuilder $g) => $g->field('note', 'Note'))
        ->create();

    $flat = $this->submitForm($form, ['details.name' => 'Ann', 'details.email' => 'ann@example.com', 'extra' => ['note' => 'hi']]);
    $draft = $this->draftForm($form, ['details.name' => 'Bea']);

    expect(Forms::submission($flat->uuid)->get()->values)->toBe(['name' => 'Ann', 'email' => 'ann@example.com', 'note' => 'hi'])
        ->and(Forms::submission($draft->uuid)->get()->value('name'))->toBe('Bea');
});
