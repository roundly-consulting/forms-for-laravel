<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Exceptions;

use RoundlyConsulting\Forms\Models\FormSubmission;

final class SubmissionNotReviewableException extends FormsException
{
    public static function for(FormSubmission $submission): self
    {
        return new self((string) trans('forms::messages.submission_not_reviewable', [
            'uuid' => $submission->uuid,
        ]));
    }

    /**
     * A review that names nobody would be open to any approver — the submitter included —
     * so it is refused rather than opened.
     */
    public static function withoutReviewers(FormSubmission $submission): self
    {
        return new self((string) trans('forms::messages.submission_review_without_reviewers', [
            'uuid' => $submission->uuid,
        ]));
    }
}
