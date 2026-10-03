<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\Approvals\Exceptions\UnauthorizedApprovalException;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
use RoundlyConsulting\Forms\Enums\SubmissionStatus;
use RoundlyConsulting\Forms\Events\SubmissionApproved;
use RoundlyConsulting\Forms\Events\SubmissionRejected;
use RoundlyConsulting\Forms\Events\SubmissionStatusChanged;
use RoundlyConsulting\Forms\Exceptions\ReviewsDisabledException;
use RoundlyConsulting\Forms\Exceptions\SubmissionNotReviewableException;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\Listeners\SyncSubmissionStatusFromApproval;
use RoundlyConsulting\Forms\Models\FormSubmission;
use RoundlyConsulting\Forms\Tests\testable\Reviewer;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

beforeEach(function () {
    config()->set('forms.approvals.enabled', true);

    if (! Schema::hasTable('reviewers')) {
        Schema::create('reviewers', function ($table): void {
            $table->id();
            $table->timestamps();
        });
    }
});

it('opens a review and moves the submission to pending', function () {
    Event::fake([SubmissionStatusChanged::class]);

    $submission = FormSubmission::factory()->create();
    $reviewer = Reviewer::query()->create();

    $request = Forms::review($submission)->requiring([$reviewer])->open();

    expect($submission->fresh()?->status)->toBe(SubmissionStatus::Pending)
        ->and($request->status)->toBe(ApprovalStatus::Pending);

    Event::assertDispatched(SubmissionStatusChanged::class);
});

it('mirrors an approval onto the submission status', function () {
    Event::fake([SubmissionApproved::class, SubmissionStatusChanged::class]);

    $submission = FormSubmission::factory()->create();
    $reviewer = Reviewer::query()->create();

    Forms::review($submission)->requiring([$reviewer])->open();
    $reviewer->approve($submission);

    expect($submission->fresh()?->status)->toBe(SubmissionStatus::Approved);
    Event::assertDispatched(SubmissionApproved::class);
});

it('mirrors a rejection with the rejecting actor', function () {
    Event::fake([SubmissionRejected::class]);

    $submission = FormSubmission::factory()->create();
    $reviewer = Reviewer::query()->create();

    Forms::review($submission)->requiring([$reviewer])->open();
    $reviewer->reject($submission, 'nope');

    expect($submission->fresh()?->status)->toBe(SubmissionStatus::Rejected);
    Event::assertDispatched(fn (SubmissionRejected $e): bool => $e->actor?->is($reviewer) === true);
});

it('resolves a quorum review once the threshold is met', function () {
    $submission = FormSubmission::factory()->create();
    $a = Reviewer::query()->create();
    $b = Reviewer::query()->create();
    $c = Reviewer::query()->create();

    Forms::review($submission)->requiring([$a, $b, $c])->quorum(2)->open();

    $a->approve($submission);
    expect($submission->fresh()?->status)->toBe(SubmissionStatus::Pending);

    $b->approve($submission);
    expect($submission->fresh()?->status)->toBe(SubmissionStatus::Approved);
});

it('opens reviews under each rule via the builder', function () {
    $reviewers = [Reviewer::query()->create(), Reviewer::query()->create()];

    $any = Forms::review(FormSubmission::factory()->create())->requiring($reviewers)->any()->open();
    expect($any->rule)->toBe(ApprovalRule::Any);

    $unanimous = Forms::review(FormSubmission::factory()->create())
        ->requiring($reviewers)->rule(ApprovalRule::Unanimous)->open();
    expect($unanimous->rule)->toBe(ApprovalRule::Unanimous);

    $weighted = Forms::review(FormSubmission::factory()->create())->requiring($reviewers)->weighted(2)->open();
    expect($weighted->rule)->toBe(ApprovalRule::Weighted)
        ->and($weighted->quorum)->toBe(2);
});

it('throws when reviews are disabled', function () {
    config()->set('forms.approvals.enabled', false);

    $submission = FormSubmission::factory()->create();

    expect(fn () => Forms::review($submission)->requiring([Reviewer::query()->create()])->open())
        ->toThrow(ReviewsDisabledException::class);
});

it('refuses to review a draft submission', function () {
    $submission = FormSubmission::factory()->draft()->create();

    expect(fn () => Forms::review($submission)->requiring([Reviewer::query()->create()])->open())
        ->toThrow(SubmissionNotReviewableException::class);
});

it('is a no-op when the listener is disabled', function () {
    $submission = FormSubmission::factory()->create();
    $reviewer = Reviewer::query()->create();

    $request = Forms::review($submission)->requiring([$reviewer])->open();

    // Disable reviews, then resolve the request directly: the listener must ignore it.
    config()->set('forms.approvals.enabled', false);
    $request->status = ApprovalStatus::Approved;
    $request->save();

    (new SyncSubmissionStatusFromApproval)->handle(new ApprovalRequestResolved($request));

    expect($submission->fresh()?->status)->toBe(SubmissionStatus::Pending);
});

it('ignores an approval request whose subject is not a submission', function () {
    $reviewer = Reviewer::query()->create();
    $submission = FormSubmission::factory()->create();
    $request = Forms::review($submission)->requiring([$reviewer])->open();

    // Repoint the request at an unrelated model; the listener must ignore it.
    $request->subject_type = $reviewer->getMorphClass();
    $request->subject_id = $reviewer->getKey();
    $request->status = ApprovalStatus::Approved;
    $request->save();

    (new SyncSubmissionStatusFromApproval)->handle(new ApprovalRequestResolved($request->fresh()));

    expect($submission->fresh()?->status)->toBe(SubmissionStatus::Pending);
});

it('is idempotent across repeated resolutions', function () {
    $submission = FormSubmission::factory()->create();
    $reviewer = Reviewer::query()->create();

    $request = Forms::review($submission)->requiring([$reviewer])->open();
    $reviewer->approve($submission);
    expect($submission->fresh()?->status)->toBe(SubmissionStatus::Approved);

    Event::fake([SubmissionApproved::class]);
    (new SyncSubmissionStatusFromApproval)->handle(new ApprovalRequestResolved($request->fresh()));

    Event::assertNotDispatched(SubmissionApproved::class);
});

it('ignores a cancelled resolution', function () {
    $submission = FormSubmission::factory()->create();
    $reviewer = Reviewer::query()->create();

    $request = Forms::review($submission)->requiring([$reviewer])->open();
    $request->status = ApprovalStatus::Cancelled;
    $request->save();

    (new SyncSubmissionStatusFromApproval)->handle(new ApprovalRequestResolved($request));

    expect($submission->fresh()?->status)->toBe(SubmissionStatus::Pending);
});

/*
 * Review fixes (2026-09-28) — the reviewers a review names are the only ones who can decide
 * it. Forms hands them to the approvals engine, which persists and enforces them; these pin
 * that from the consumer side, so a regression in either package reds here.
 */

it('refuses a decision from anyone the review does not name, the submitter included', function () {
    $submitter = Reviewer::query()->create();
    $lead = Reviewer::query()->create();
    $qa = Reviewer::query()->create();
    $outsider = Reviewer::query()->create();

    $submission = FormSubmission::factory()->create([
        'sender_id' => $submitter->getKey(),
        'sender_type' => $submitter->getMorphClass(),
    ]);

    Forms::review($submission)->requiring([$lead, $qa])->quorum(1)->open();

    expect(fn () => $submitter->approve($submission))->toThrow(UnauthorizedApprovalException::class)
        ->and(fn () => $outsider->approve($submission))->toThrow(UnauthorizedApprovalException::class)
        ->and(fn () => $outsider->reject($submission, 'veto'))->toThrow(UnauthorizedApprovalException::class)
        ->and($submission->fresh()?->status)->toBe(SubmissionStatus::Pending);

    $qa->approve($submission);

    expect($submission->fresh()?->status)->toBe(SubmissionStatus::Approved);
});

it('refuses to open a review that names no reviewers', function () {
    $submission = FormSubmission::factory()->create();

    expect(fn () => Forms::review($submission)->open())
        ->toThrow(SubmissionNotReviewableException::class, 'without naming its reviewers')
        ->and($submission->fresh()?->status)->toBe(SubmissionStatus::Final);
});

it('resolves a re-review when the same reviewer approves again', function () {
    $submission = FormSubmission::factory()->create();
    $lead = Reviewer::query()->create();

    Forms::review($submission)->requiring([$lead])->open();
    $lead->approve($submission);
    expect($submission->fresh()?->status)->toBe(SubmissionStatus::Approved);

    Forms::review($submission->fresh())->requiring([$lead])->open();
    expect($submission->fresh()?->status)->toBe(SubmissionStatus::Pending);

    $lead->approve($submission);
    expect($submission->fresh()?->status)->toBe(SubmissionStatus::Approved);
});

it('fires SubmissionApproved once when two resolutions race for the same submission', function () {
    $submission = FormSubmission::factory()->create();
    $reviewer = Reviewer::query()->create();

    $request = Forms::review($submission)->requiring([$reviewer])->open();

    // Both resolutions read the submission while it was still pending…
    $stale = $submission->fresh();
    $request->status = ApprovalStatus::Approved;
    $request->save();

    // …the first one applies…
    (new SyncSubmissionStatusFromApproval)->handle(new ApprovalRequestResolved($request->fresh()));

    // …and the second arrives carrying its stale, still-pending copy.
    Event::fake([SubmissionApproved::class, SubmissionStatusChanged::class]);
    $late = $request->fresh();
    $late?->setRelation('subject', $stale);
    (new SyncSubmissionStatusFromApproval)->handle(new ApprovalRequestResolved($late));

    Event::assertNotDispatched(SubmissionApproved::class);
    Event::assertNotDispatched(SubmissionStatusChanged::class);
    expect($submission->fresh()?->status)->toBe(SubmissionStatus::Approved);
});

it('reads the env-backed enabled flag as a boolean string', function (string $flag, bool $enabled) {
    config()->set('forms.approvals.enabled', $flag);

    $open = fn () => Forms::review(FormSubmission::factory()->create())
        ->requiring([Reviewer::query()->create()])
        ->open();

    $enabled
        ? expect($open())->toBeInstanceOf(ApprovalRequest::class)
        : expect($open)->toThrow(ReviewsDisabledException::class);
})->with([
    ['true', true],
    ['1', true],
    ['on', true],
    ['false', false],
    ['0', false],
    ['', false],
]);

it('throws on an enabled-flag typo instead of reading it as off (strict config)', function () {
    config()->set('forms.approvals.enabled', 'disabled');

    expect(fn () => Forms::review(FormSubmission::factory()->create())->requiring([Reviewer::query()->create()])->open())
        ->toThrow(
            InvalidConfigurationException::class,
            'Configuration value [forms.approvals.enabled] must be a boolean (true/false, 1/0, on/off or yes/no), [disabled] given.',
        );
});
