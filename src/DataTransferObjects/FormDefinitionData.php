<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\DataTransferObjects;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

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

        return new self(
            key: (string) $data['key'],
            name: (string) $data['name'],
            expiresAt: self::expiresAt($data['expiresAt'] ?? $data['expires_at'] ?? null),
            isPublic: (bool) ($data['isPublic'] ?? $data['is_public'] ?? false),
            groups: array_map(
                static fn (array $group): GroupDefinitionData => GroupDefinitionData::fromArray($group),
                $groups,
            ),
        );
    }

    /**
     * A definition read from config carries its expiry as a date string or a Unix
     * timestamp as often as a Carbon instance; each reads as the same moment. Not set — null
     * or blank (`''`, whitespace) — means none, never "now".
     *
     * @throws InvalidArgumentException when the value is not a date at all
     */
    private static function expiresAt(mixed $value): ?CarbonInterface
    {
        return match (true) {
            $value === null, is_string($value) && trim($value) === '' => null,
            $value instanceof CarbonInterface => $value,
            $value instanceof DateTimeInterface => Carbon::instance($value),
            is_int($value) => Carbon::createFromTimestamp($value, date_default_timezone_get()),
            is_string($value) => Carbon::parse($value),
            default => throw new InvalidArgumentException('A form definition\'s expires_at must be a date string, a timestamp or a date, '.get_debug_type($value).' given.'),
        };
    }
}
