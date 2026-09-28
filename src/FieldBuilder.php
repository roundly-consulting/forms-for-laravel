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

    /** @var array<string, string>|null */
    private ?array $messages = null;

    /** @var list<array<string, mixed>> */
    private array $conditions = [];

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

    /**
     * @param  array<array-key, mixed>  $rules
     * @param  array<string, string>  $messages
     */
    public function rules(array $rules, array $messages = []): self
    {
        $this->rules = $rules;

        if ($messages !== []) {
            $this->messages = array_merge($this->messages ?? [], $messages);
        }

        return $this;
    }

    /** @param  array<string, string>  $messages */
    public function messages(array $messages): self
    {
        $this->messages = array_merge($this->messages ?? [], $messages);

        return $this;
    }

    public function required(): self
    {
        $this->rules = $this->withRule('required');

        return $this;
    }

    public function email(?string $key = null, ?string $name = null): self
    {
        return $this->type('email')->rules($this->withRule('email'));
    }

    public function textarea(): self
    {
        return $this->type('textarea');
    }

    public function checkbox(): self
    {
        return $this->type('checkbox')->rules($this->withRule('boolean'));
    }

    public function number(): self
    {
        return $this->type('number')->rules($this->withRule('numeric'));
    }

    public function date(): self
    {
        return $this->type('date')->rules($this->withRule('date'));
    }

    public function file(): self
    {
        return $this->type('file');
    }

    /** @param  array<array-key, mixed>  $options */
    public function select(array $options): self
    {
        return $this->type('select')->options($options);
    }

    /**
     * Show this field only when another field of the same form matches a value. `$field` is
     * that field's key — looked up in this field's group first, then in the form's other
     * groups — or `group_key.field_key` to name it exactly.
     */
    public function visibleWhen(string $field, mixed $value, string $operator = '='): self
    {
        $this->conditions[] = [
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    /**
     * Require this field only when another field of the same form matches a value (the
     * reference resolves as in {@see self::visibleWhen()}). Pairs the stored condition with
     * a `required` rule that the validator enforces when the condition is met.
     */
    public function requiredWhen(string $field, mixed $value, string $operator = '='): self
    {
        $this->required();

        return $this->visibleWhen($field, $value, $operator);
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
            conditions: $this->conditions === [] ? null : $this->conditions,
            messages: $this->messages,
        );
    }

    /**
     * @return list<mixed>
     */
    private function withRule(string $rule): array
    {
        $rules = $this->rules ?? [];

        if (! in_array($rule, $rules, true)) {
            $rules[] = $rule;
        }

        return array_values($rules);
    }
}
