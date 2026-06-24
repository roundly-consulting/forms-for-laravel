<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\DataTransferObjects;

use Carbon\CarbonInterface;

final readonly class FormDefinitionData
{
    /** @param  list<GroupDefinitionData>  $groups */
    public function __construct(
        public string $key,
        public string $name,
        public ?CarbonInterface $expiresAt = null,
        public bool $isPublic = false,
        public array $groups = [],
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        /** @var list<array<string, mixed>> $groups */
        $groups = $data['groups'] ?? [];

        /** @var CarbonInterface|null $expiresAt */
        $expiresAt = $data['expiresAt'] ?? $data['expires_at'] ?? null;

        return new self(
            key: (string) $data['key'],
            name: (string) $data['name'],
            expiresAt: $expiresAt,
            isPublic: (bool) ($data['isPublic'] ?? $data['is_public'] ?? false),
            groups: array_map(
                static fn (array $group): GroupDefinitionData => GroupDefinitionData::fromArray($group),
                $groups,
            ),
        );
    }
}
