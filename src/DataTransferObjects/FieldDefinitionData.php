<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\DataTransferObjects;

final readonly class FieldDefinitionData
{
    /**
     * @param  array<array-key, mixed>|null  $options
     * @param  array<array-key, mixed>|null  $validations
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $type = 'text',
        public ?string $help = null,
        public ?string $autofill = null,
        public ?array $options = null,
        public ?array $validations = null,
        public int $order = 0,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        /** @var array<array-key, mixed>|null $options */
        $options = $data['options'] ?? null;
        /** @var array<array-key, mixed>|null $validations */
        $validations = $data['validations'] ?? null;

        return new self(
            key: (string) $data['key'],
            name: (string) $data['name'],
            type: isset($data['type']) ? (string) $data['type'] : 'text',
            help: isset($data['help']) ? (string) $data['help'] : null,
            autofill: isset($data['autofill']) ? (string) $data['autofill'] : null,
            options: $options,
            validations: $validations,
            order: isset($data['order']) ? (int) $data['order'] : 0,
        );
    }
}
