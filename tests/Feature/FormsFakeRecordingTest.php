<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\GroupBuilder;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\FormSubmission;
use RoundlyConsulting\Forms\Tests\testable\Reviewer;
use RoundlyConsulting\Forms\Tests\testable\Submitter;

function recordingForm(): Form
{
    return Forms::define('poll', 'Poll')
        ->public()
        ->group('g', 'G', fn (GroupBuilder $g) => $g->field('answer', 'Answer'))
        ->create();
}

function recordingRequest(string $answer): Request
{
    return Request::create('t', parameters: ['poll' => ['g' => ['answer' => $answer]]]);
}

it('records reviews and still opens the approvals request', function (): void {
    config()->set('forms.approvals.enabled', true);

    if (! Schema::hasTable('reviewers')) {
        Schema::create('reviewers', function ($table): void {
            $table->id();
            $table->timestamps();
        });
    }

    $submission = FormSubmission::factory()->create();
    $other = FormSubmission::factory()->create();
    $reviewer = Reviewer::query()->create();
    $fake = Forms::fake();

    $fake->assertNothingReviewed();
    expect(fn () => $fake->assertReviewOpened())->toThrow(AssertionFailedError::class, 'Expected a review to be opened');

    $request = Forms::submission($submission)->review()->requiring([$reviewer])->quorum(1)->open();

    $fake->assertReviewOpened();
    $fake->assertReviewOpened(fn (ApprovalRequest $r, FormSubmission $s): bool => $s->is($submission)
        && $r->rule === ApprovalRule::Quorum
        && $r->quorum === 1);

    expect($request->exists)->toBeTrue()
        ->and(fn () => $fake->assertReviewOpened(fn (ApprovalRequest $r, FormSubmission $s): bool => $s->is($other)))
        ->toThrow(AssertionFailedError::class, 'Expected a review matching the callback')
        ->and(fn () => $fake->assertNothingReviewed())->toThrow(AssertionFailedError::class, 'Expected no review');
});

it('records submissions and drafts made through the HasForms trait', function (): void {
    $form = recordingForm();
    $sender = Submitter::query()->create();
    $fake = Forms::fake();

    $sender->submitTo($form, recordingRequest('yes'));
    $sender->draftTo($form, recordingRequest('maybe'));

    $fake->assertSubmitted($form);
    $fake->assertDrafted($form);
});

it('records finalization through a submission handle', function (): void {
    $form = recordingForm();
    $fake = Forms::fake();

    $fake->assertNothingFinalized();

    $draft = Forms::draft($form, recordingRequest('yes'));
    Forms::submission($draft->uuid)->finalize();

    $fake->assertFinalized($draft->uuid);
    expect(fn () => $fake->assertNothingFinalized())->toThrow(AssertionFailedError::class, 'finalized');
});

it('asserts nothing was drafted', function (): void {
    $form = recordingForm();
    $fake = Forms::fake();

    $fake->assertNothingDrafted();

    Forms::draft($form, recordingRequest('yes'));

    expect(fn () => $fake->assertNothingDrafted())->toThrow(AssertionFailedError::class, 'drafted');
});

it('asserts no form was defined, created or updated', function (): void {
    $fake = Forms::fake();

    $fake->assertNoFormDefined();
    $fake->assertNoFormCreated();
    $fake->assertNoFormUpdated();

    recordingForm();
    Forms::update('poll')->name('Renamed poll')->save();

    $fake->assertFormUpdated('poll');

    expect(fn () => $fake->assertNoFormDefined())->toThrow(AssertionFailedError::class, 'defined')
        ->and(fn () => $fake->assertNoFormCreated())->toThrow(AssertionFailedError::class, 'created')
        ->and(fn () => $fake->assertNoFormUpdated())->toThrow(AssertionFailedError::class, 'updated')
        ->and(fn () => $fake->assertFormUpdated('other'))->toThrow(AssertionFailedError::class);
});

it('records an update only once it is saved', function (): void {
    recordingForm();
    $fake = Forms::fake();

    Forms::update('poll')->name('Unsaved');

    $fake->assertNoFormUpdated();
});

it('asserts no submission row was created', function (): void {
    $form = recordingForm();
    $fake = Forms::fake();

    $fake->assertNoSubmissionCreated();

    Forms::createSubmission($form->fields->first(), ['value' => 'x']);

    expect(fn () => $fake->assertNoSubmissionCreated())->toThrow(AssertionFailedError::class, 'submission row');
});

it('asserts nothing was synced', function (): void {
    $fake = Forms::fake();

    $fake->assertNothingSynced();
    expect(fn () => $fake->assertSynced())->toThrow(AssertionFailedError::class);

    Forms::sync([]);

    expect(fn () => $fake->assertNothingSynced())->toThrow(AssertionFailedError::class, 'synced');
});
