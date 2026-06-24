<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\DataTransferObjects;

final readonly class GroupDefinitionData
{
    /** @param  list<FieldDefinitionData>  $fields */
    public function __construct(
        public string $key,
        public string $name,
        public int $order = 0,
        public array $fields = [],
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        /** @var list<array<string, mixed>> $fields */
        $fields = $data['fields'] ?? [];

        return new self(
            key: (string) $data['key'],
            name: (string) $data['name'],
            order: isset($data['order']) ? (int) $data['order'] : 0,
            fields: array_map(
                static fn (array $field): FieldDefinitionData => FieldDefinitionData::fromArray($field),
                $fields,
            ),
        );
    }
}
