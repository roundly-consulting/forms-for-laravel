<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Actions;

use RoundlyConsulting\Forms\DataTransferObjects\FormDefinitionData;
use RoundlyConsulting\Forms\Support\FormModel;
use RoundlyConsulting\Forms\Support\FormsConfig;

/**
 * Syncs declaratively-defined forms (from config or a passed list) into the
 * database: creates missing forms and updates changed ones. Idempotent.
 */
final readonly class SyncFormsAction
{
    public function __construct(
        private CreateFormAction $createForm,
        private UpdateFormAction $updateForm,
    ) {}

    /**
     * @param  list<array<string, mixed>>|null  $definitions
     * @return list<string> the keys that were synced
     */
    public function execute(?array $definitions = null): array
    {
        if ($definitions === null) {
            $definitions = FormsConfig::definitions();
        }

        $formModel = FormModel::class();

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
