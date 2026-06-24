<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms;

use RoundlyConsulting\Forms\DataTransferObjects\FieldDefinitionData;

final class FieldBuilder
{
    private string $type = 'text';

    private ?string $help = null;

    private ?string $autofill = null;

    /** @var array<array-key, mixed>|null */
    private ?array $options = null;

    /** @var array<array-key, mixed>|null */
    private ?array $rules = null;

    private ?int $order = null;

    public function __construct(
        private readonly string $key,
        private readonly string $name,
    ) {}

    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function help(string $help): self
    {
        $this->help = $help;

        return $this;
    }

    public function autofill(string $autofill): self
    {
        $this->autofill = $autofill;

        return $this;
    }

    /** @param  array<array-key, mixed>  $options */
    public function options(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    /** @param  array<array-key, mixed>  $rules */
    public function rules(array $rules): self
    {
        $this->rules = $rules;

        return $this;
    }

    public function order(int $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function toData(int $defaultOrder): FieldDefinitionData
    {
        return new FieldDefinitionData(
            key: $this->key,
            name: $this->name,
            type: $this->type,
            help: $this->help,
            autofill: $this->autofill,
            options: $this->options,
            validations: $this->rules,
            order: $this->order ?? $defaultOrder,
        );
    }
}
