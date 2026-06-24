<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Actions;

use RoundlyConsulting\Forms\DataTransferObjects\FormDefinitionData;
use RoundlyConsulting\Forms\Models\Form;

/**
 * Syncs declaratively-defined forms (from config or a passed list) into the
 * database: creates missing forms and updates changed ones. Idempotent.
 */
final class SyncFormsAction
{
    public function __construct(
        private readonly CreateFormAction $createForm = new CreateFormAction,
        private readonly UpdateFormAction $updateForm = new UpdateFormAction,
    ) {}

    /**
     * @param  list<array<string, mixed>>|null  $definitions
     * @return list<string> the keys that were synced
     */
    public function execute(?array $definitions = null): array
    {
        if ($definitions === null) {
            /** @var list<array<string, mixed>> $definitions */
            $definitions = config('forms.definitions', []);
        }

        /** @var class-string<Form> $formModel */
        $formModel = config('forms.models.form', Form::class);

        $synced = [];

        foreach ($definitions as $definition) {
            $data = FormDefinitionData::fromArray($definition);

            $exists = $formModel::query()->forKey($data->key)->exists();

            if ($exists) {
                $this->updateForm->execute($data);
            } else {
                $this->createForm->execute($data);
            }

            $synced[] = $data->key;
        }

        return $synced;
    }
}
