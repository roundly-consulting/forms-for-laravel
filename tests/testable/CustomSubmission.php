<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Tests\testable;

use RoundlyConsulting\Forms\Models\Submission;

final class CustomSubmission extends Submission
{
    protected $table = 'submissions';
}
