<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms;

use Carbon\CarbonInterface;
use Closure;
use RoundlyConsulting\Forms\Actions\CreateFormAction;
use RoundlyConsulting\Forms\DataTransferObjects\FormDefinitionData;
use RoundlyConsulting\Forms\DataTransferObjects\GroupDefinitionData;
use RoundlyConsulting\Forms\Models\Form;

final class FormBuilder
{
    private ?CarbonInterface $expiresAt = null;

    private bool $isPublic = false;

    /** @var list<GroupBuilder> */
    private array $groups = [];

    public function __construct(
        private readonly CreateFormAction $createForm,
        private readonly string $key,
        private readonly string $name,
    ) {}

    public function public(bool $public = true): self
    {
        $this->isPublic = $public;

        return $this;
    }

    public function expiresAt(CarbonInterface $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    /** @param  Closure(GroupBuilder): void|null  $callback */
    public function group(string $key, string $name, ?Closure $callback = null): self
    {
        $group = new GroupBuilder($key, $name);

        if ($callback !== null) {
            $callback($group);
        }

        $this->groups[] = $group;

        return $this;
    }

    public function toData(): FormDefinitionData
    {
        return new FormDefinitionData(
            key: $this->key,
            name: $this->name,
            expiresAt: $this->expiresAt,
            isPublic: $this->isPublic,
            groups: array_map(
                static fn (GroupBuilder $group, int $index): GroupDefinitionData => $group->toData($index),
                $this->groups,
                array_keys($this->groups),
            ),
        );
    }

    public function create(): Form
    {
        return $this->createForm->execute($this->toData());
    }
}
