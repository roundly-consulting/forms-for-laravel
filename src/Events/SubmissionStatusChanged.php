<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RoundlyConsulting\Forms\Enums\SubmissionStatus;
use RoundlyConsulting\Forms\Models\FormSubmission;

final class SubmissionStatusChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public FormSubmission $submission,
        public SubmissionStatus $from,
        public SubmissionStatus $to,
    ) {}
}
