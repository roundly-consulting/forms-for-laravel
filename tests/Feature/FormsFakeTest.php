<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Forms\DataTransferObjects\FormDefinitionData;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\GroupBuilder;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Services\FormsService;
use RoundlyConsulting\Forms\Testing\FormsFake;

function fakeContactForm(): Form
{
    return Forms::define('contact', 'Contact us')
        ->public()
        ->group('details', 'Details', function (GroupBuilder $g): void {
            $g->field('name', 'Name');
        })
        ->create();
}

/** @param  array<string, mixed>  $values */
function fakeRequest(Form $form, array $values): Request
{
    return Request::create('testing', 'POST', [$form->key => $values]);
}

it('swaps the bound manager for a recording fake', function () {
    $fake = Forms::fake();

    // `forms.manager` is aliased to FormsService::class, so resolving the typed
    // alias proves the container binding was swapped for the fake.
    expect($fake)->toBeInstanceOf(FormsFake::class);
    expect(app(FormsService::class))->toBe($fake);
});

it('records a fluent form definition', function () {
    $fake = Forms::fake();

    fakeContactForm();

    $fake->assertFormDefined('contact');
});

it('records a programmatic form creation', function () {
    $fake = Forms::fake();

    Forms::create(new FormDefinitionData('lead', 'Lead', null, true, []));

    $fake->assertFormCreated();
    $fake->assertFormCreated(fn (Form $form): bool => $form->key === 'lead');
    $fake->assertFormDefined('lead');
});

it('records submissions and supports filters', function () {
    $fake = Forms::fake();
    $form = fakeContactForm();

    $result = Forms::submit($form, fakeRequest($form, ['details' => ['name' => 'Ann']]));

    $fake->assertSubmitted();
    $fake->assertSubmitted($form);
    $fake->assertSubmittedCount(1);
    $fake->assertSubmitted($form, fn ($r): bool => $r->uuid === $result->uuid && $r->fieldCount === 1);
});

it('records drafts and finalization', function () {
    $fake = Forms::fake();
    $form = fakeContactForm();

    $draft = Forms::draft($form, fakeRequest($form, ['details' => ['name' => 'Ann']]));
    $fake->assertDrafted($form);
    $fake->assertDrafted($form, fn ($r): bool => $r->uuid === $draft->uuid);

    Forms::finalize($draft->uuid);
    $fake->assertFinalized($draft->uuid);
    $fake->assertFinalized();
});

it('records form updates', function () {
    $fake = Forms::fake();
    fakeContactForm();

    Forms::update('contact')->name('Reach us')->save();

    $fake->assertFormUpdated('contact');
});

it('records direct submission creation', function () {
    $fake = Forms::fake();
    fakeContactForm();
    $field = Field::query()->where('key', 'name')->sole();

    Forms::createSubmission($field, ['value' => 'Ann']);

    $fake->assertSubmissionCreated();
    $fake->assertSubmissionCreated(fn (mixed $s): bool => $s->field_id === $field->getKey());
});

it('records a config sync', function () {
    $fake = Forms::fake();

    $keys = Forms::sync([
        ['key' => 'newsletter', 'name' => 'Newsletter', 'groups' => []],
    ]);

    expect($keys)->toBe(['newsletter']);
    $fake->assertSynced();
});

it('asserts the negative space', function () {
    $fake = Forms::fake();
    $form = fakeContactForm();

    $fake->assertNothingSubmitted();
    $fake->assertNotSubmitted();
    $fake->assertNotSubmitted($form);
});

it('fails loudly when an expected submission is missing', function () {
    $fake = Forms::fake();
    $form = fakeContactForm();

    expect(fn () => $fake->assertSubmitted($form))
        ->toThrow(AssertionFailedError::class);
});

it('fails matcher callbacks that do not match', function () {
    $fake = Forms::fake();
    $form = fakeContactForm();

    Forms::submit($form, fakeRequest($form, ['details' => ['name' => 'Ann']]));
    Forms::create(new FormDefinitionData('lead', 'Lead', null, true, []));
    $field = Field::query()->where('key', 'name')->sole();
    Forms::createSubmission($field, ['value' => 'x']);

    expect(fn () => $fake->assertSubmitted($form, fn (): bool => false))->toThrow(AssertionFailedError::class);
    expect(fn () => $fake->assertDrafted($form))->toThrow(AssertionFailedError::class);
    expect(fn () => $fake->assertDrafted())->toThrow(AssertionFailedError::class);
    expect(fn () => $fake->assertFormCreated(fn (): bool => false))->toThrow(AssertionFailedError::class);
    expect(fn () => $fake->assertSubmissionCreated(fn (): bool => false))->toThrow(AssertionFailedError::class);
    expect(fn () => $fake->assertFinalized('missing-uuid'))->toThrow(AssertionFailedError::class);
    expect(fn () => $fake->assertNotSubmitted($form))->toThrow(AssertionFailedError::class);
});
