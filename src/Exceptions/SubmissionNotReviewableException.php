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
}
