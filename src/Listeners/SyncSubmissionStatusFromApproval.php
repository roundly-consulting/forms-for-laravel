<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Listeners;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
use RoundlyConsulting\Forms\Enums\SubmissionStatus;
use RoundlyConsulting\Forms\Events\SubmissionApproved;
use RoundlyConsulting\Forms\Events\SubmissionRejected;
use RoundlyConsulting\Forms\Events\SubmissionStatusChanged;
use RoundlyConsulting\Forms\Models\FormSubmission;

/**
 * Mirrors an approval request's resolution onto the {@see SubmissionStatus} of
 * its FormSubmission subject, so engine-driven outcomes (quorum reached, a
 * direct decision, a staged pipeline clearing) move the submission and fire the
 * forms review events — regardless of how the decision arrived.
 *
 * The listener is idempotent (a no-op when already at the target status) and
 * guard-aware (only applies transitions allowed by the submission lifecycle
 * graph).
 */
final class SyncSubmissionStatusFromApproval
{
    public function handle(ApprovalRequestResolved $event): void
    {
        if (! (bool) config('forms.approvals.enabled', false)) {
            return;
        }

        $request = $event->request;
        $subject = $request->subject;

        if (! $subject instanceof FormSubmission) {
            return;
        }

        $target = $this->map($request->status);

        if ($target === null) {
            return;
        }

        $from = $subject->status;

        if ($from === $target || ! $from->canTransitionTo($target)) {
            return;
        }

        $subject->update(['status' => $target]);

        event(new SubmissionStatusChanged($subject, $from, $target));

        if ($target === SubmissionStatus::Approved) {
            event(new SubmissionApproved($subject));
        }

        if ($target === SubmissionStatus::Rejected) {
            event(new SubmissionRejected($subject, $this->rejectingActor($request)));
        }
    }

    private function map(ApprovalStatus $status): ?SubmissionStatus
    {
        return match ($status) {
            ApprovalStatus::Approved => SubmissionStatus::Approved,
            ApprovalStatus::Rejected => SubmissionStatus::Rejected,
            ApprovalStatus::Pending,
            ApprovalStatus::Cancelled,
            ApprovalStatus::Expired => null,
        };
    }

    private function rejectingActor(ApprovalRequest $request): ?Model
    {
        $decision = $request->decisions()
            ->where('status', ApprovalStatus::Rejected)
            ->latest('id')
            ->first();

        $actor = $decision?->actor;

        return $actor instanceof Model ? $actor : null;
    }
}
