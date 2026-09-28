<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
use RoundlyConsulting\Forms\Enums\SubmissionStatus;
use RoundlyConsulting\Forms\Events\SubmissionStatusChanged;
use RoundlyConsulting\Forms\Exceptions\ReviewsDisabledException;
use RoundlyConsulting\Forms\Exceptions\SubmissionNotReviewableException;
use RoundlyConsulting\Forms\Listeners\SyncSubmissionStatusFromApproval;
use RoundlyConsulting\Forms\Models\FormSubmission;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * Opens an approvals-engine request over a whole submission and moves it into
 * the {@see SubmissionStatus::Pending} state, so the submission is resolved by
 * the {@see SyncSubmissionStatusFromApproval}
 * listener as decisions come in. Only the reviewers it names (or their delegates)
 * can decide it.
 */
final readonly class ReviewSubmissionAction
{
    /** @param  list<Model>  $approvers */
    public function execute(
        FormSubmission $submission,
        array $approvers,
        ApprovalRule $rule = ApprovalRule::Unanimous,
        ?int $quorum = null,
    ): ApprovalRequest {
        if (! Config::boolean('forms.approvals.enabled')) {
            throw ReviewsDisabledException::make();
        }

        if (! $submission->status->isReviewable()) {
            throw SubmissionNotReviewableException::for($submission);
        }

        // The named reviewers are handed to the approvals engine, which stores them and
        // refuses a decision from anyone else. A request naming nobody is open to every
        // approver — the submitter included — so it never opens here.
        if ($approvers === []) {
            throw SubmissionNotReviewableException::withoutReviewers($submission);
        }

        $request = $submission->requestApproval($approvers, $rule, $quorum);

        $from = $submission->status;

        if ($from !== SubmissionStatus::Pending) {
            $submission->update(['status' => SubmissionStatus::Pending]);

            event(new SubmissionStatusChanged($submission, $from, SubmissionStatus::Pending));
        }

        return $request;
    }
}
