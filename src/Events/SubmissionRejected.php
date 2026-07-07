<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RoundlyConsulting\Forms\Models\FormSubmission;

final class SubmissionRejected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public FormSubmission $submission,
        public ?Model $actor = null,
    ) {}
}
