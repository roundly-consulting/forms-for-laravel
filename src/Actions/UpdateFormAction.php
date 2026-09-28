<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Actions;

use RoundlyConsulting\Forms\DataTransferObjects\FieldDefinitionData;
use RoundlyConsulting\Forms\DataTransferObjects\FormDefinitionData;
use RoundlyConsulting\Forms\DataTransferObjects\GroupDefinitionData;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Support\FieldModel;
use RoundlyConsulting\Forms\Support\FormModel;
use RoundlyConsulting\Forms\Support\GroupModel;

/**
 * Edits an existing form's structure by diffing the supplied definition against
 * the persisted records: matching groups/fields are updated only when changed,
 * missing ones are created. Eloquent's model events fire for every changed
 * record. Records absent from the definition are left untouched.
 */
final readonly class UpdateFormAction
{
    public function execute(FormDefinitionData $data): Form
    {
        $formModel = FormModel::class();

        /** @var Form $form */
        $form = $formModel::query()
            ->forKey($data->key)
            ->with(['groups.fields'])
            ->sole();

        return $form->getConnection()->transaction(function () use ($form, $data): Form {
            $this->syncForm($form, $data);

            foreach ($data->groups as $groupOrder => $groupData) {
                $this->syncGroup($form, $groupData, $groupOrder);
            }

            return $form->refresh()->load(['groups.fields']);
        });
    }

    private function syncForm(Form $form, FormDefinitionData $data): void
    {
        $changes = array_filter([
            'name' => $data->name,
            'is_public' => $data->isPublic,
            'expires_at' => $data->expiresAt,
        ], fn (mixed $value, string $key): bool => $form->{$key} != $value, ARRAY_FILTER_USE_BOTH);

        if ($changes !== []) {
            $form->update($changes);
        }
    }

    private function syncGroup(Form $form, GroupDefinitionData $data, int $defaultOrder): Group
    {
        $groupModel = GroupModel::class();

        $order = $data->order !== 0 ? $data->order : $defaultOrder;

        $group = $form->groups->firstWhere('key', $data->key);

        if (! $group instanceof Group) {
            /** @var Group $group */
            $group = $groupModel::query()->create([
                'form_id' => $form->getKey(),
                'key' => $data->key,
                'name' => $data->name,
                'order' => $order,
            ]);
        } else {
            $changes = array_filter([
                'name' => $data->name,
                'order' => $order,
            ], fn (mixed $value, string $key): bool => $group->{$key} != $value, ARRAY_FILTER_USE_BOTH);

            if ($changes !== []) {
                $group->update($changes);
            }
        }

        foreach ($data->fields as $fieldOrder => $fieldData) {
            $this->syncField($form, $group, $fieldData, $fieldOrder);
        }

        return $group;
    }

    private function syncField(Form $form, Group $group, FieldDefinitionData $data, int $defaultOrder): Field
    {
        $fieldModel = FieldModel::class();

        $order = $data->order !== 0 ? $data->order : $defaultOrder;

        $attributes = [
            'name' => $data->name,
            'type' => $data->type,
            'help' => $data->help,
            'autofill' => $data->autofill,
            'options' => $data->options,
            'validations' => $data->validations,
            'conditions' => $data->conditions,
            'messages' => $data->messages,
            'order' => $order,
        ];

        $field = $group->relationLoaded('fields')
            ? $group->fields->firstWhere('key', $data->key)
            : null;

        if (! $field instanceof Field) {
            /** @var Field $field */
            $field = $fieldModel::query()->create(array_merge($attributes, [
                'form_id' => $form->getKey(),
                'group_id' => $group->getKey(),
                'key' => $data->key,
            ]));

            return $field;
        }

        $changes = array_filter(
            $attributes,
            fn (mixed $value, string $key): bool => $field->{$key} != $value,
            ARRAY_FILTER_USE_BOTH,
        );

        if ($changes !== []) {
            $field->update($changes);
        }

        return $field;
    }
}
