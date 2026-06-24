<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms;

use RoundlyConsulting\Forms\DataTransferObjects\FieldDefinitionData;
use RoundlyConsulting\Forms\DataTransferObjects\GroupDefinitionData;

final class GroupBuilder
{
    /** @var list<FieldBuilder> */
    private array $fields = [];

    private ?int $order = null;

    public function __construct(
        private readonly string $key,
        private readonly string $name,
    ) {}

    public function field(string $key, string $name): FieldBuilder
    {
        $field = new FieldBuilder($key, $name);

        $this->fields[] = $field;

        return $field;
    }

    public function order(int $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function toData(int $defaultOrder): GroupDefinitionData
    {
        return new GroupDefinitionData(
            key: $this->key,
            name: $this->name,
            order: $this->order ?? $defaultOrder,
            fields: array_map(
                static fn (FieldBuilder $field, int $index): FieldDefinitionData => $field->toData($index),
                $this->fields,
                array_keys($this->fields),
            ),
        );
    }
}
