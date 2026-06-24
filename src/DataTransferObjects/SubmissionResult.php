<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\DataTransferObjects;

use Carbon\CarbonInterface;

final readonly class SubmissionResult
{
    public function __construct(
        public string $uuid,
        public int $fieldCount,
        public CarbonInterface $submittedAt,
    ) {}
}
