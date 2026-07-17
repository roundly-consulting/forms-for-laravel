<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Tests\testable;

use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Testing\Fixtures\Concerns\CountsCreations;

final class CustomSubmission extends Submission
{
    /**
     * Counting `created` events on this exact class is the independent oracle a swap
     * really took effect. Without it `toHonourModelSwap` silently downgrades to an
     * `instanceof` check, which a row created as the *packaged* class can still pass.
     */
    use CountsCreations;

    protected $table = 'submissions';
}
