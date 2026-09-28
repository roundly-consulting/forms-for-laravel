<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms;

use Carbon\CarbonInterface;
use Closure;
use RoundlyConsulting\Forms\DataTransferObjects\FormDefinitionData;
use RoundlyConsulting\Forms\DataTransferObjects\GroupDefinitionData;
use RoundlyConsulting\Forms\Models\Form;

/**
 * Fluent editor for an existing form. Mirrors FormBuilder ergonomics but the
 * resulting definition is diffed against the persisted structure on save(), which goes
 * through the manager so host overrides and `Forms::fake()` see it.
 */
final class UpdateFormBuilder
{
    private ?string $name = null;

    private ?CarbonInterface $expiresAt = null;

    private ?bool $isPublic = null;

    /** @var list<GroupBuilder> */
    private array $groups = [];

    /**
     * @internal build it with `Forms::update($key)`
     */
    public function __construct(
        private readonly FormsManager $forms,
        private readonly Form $form,
    ) {}

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

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
            key: $this->form->key,
            name: $this->name ?? $this->form->name,
            expiresAt: $this->expiresAt ?? $this->form->expires_at,
            isPublic: $this->isPublic ?? $this->form->is_public,
            groups: array_map(
                static fn (GroupBuilder $group, int $index): GroupDefinitionData => $group->toData($index),
                $this->groups,
                array_keys($this->groups),
            ),
        );
    }

    public function save(): Form
    {
        return $this->forms->updateFrom($this->toData());
    }
}
