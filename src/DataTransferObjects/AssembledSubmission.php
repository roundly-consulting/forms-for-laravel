<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\DataTransferObjects;

use Carbon\CarbonInterface;

final readonly class AssembledSubmission
{
    /** @param  array<string, mixed>  $values */
    public function __construct(
        public string $uuid,
        public array $values,
        public ?int $senderId,
        public ?string $senderType,
        public CarbonInterface $submittedAt,
    ) {}

    public function value(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }
}
