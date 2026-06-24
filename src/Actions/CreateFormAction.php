<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Actions;

use RoundlyConsulting\Forms\DataTransferObjects\FieldDefinitionData;
use RoundlyConsulting\Forms\DataTransferObjects\FormDefinitionData;
use RoundlyConsulting\Forms\DataTransferObjects\GroupDefinitionData;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;

final class CreateFormAction
{
    public function execute(FormDefinitionData $data): Form
    {
        /** @var class-string<Form> $formModel */
        $formModel = config('forms.models.form', Form::class);

        return $formModel::query()->getConnection()->transaction(function () use ($data, $formModel): Form {
            /** @var Form $form */
            $form = $formModel::query()->create([
                'key' => $data->key,
                'name' => $data->name,
                'expires_at' => $data->expiresAt,
                'is_public' => $data->isPublic,
            ]);

            foreach ($data->groups as $groupOrder => $groupData) {
                $this->createGroup($form, $groupData, $groupOrder);
            }

            return $form;
        });
    }

    private function createGroup(Form $form, GroupDefinitionData $data, int $defaultOrder): Group
    {
        /** @var class-string<Group> $groupModel */
        $groupModel = config('forms.models.group', Group::class);

        /** @var Group $group */
        $group = $groupModel::query()->create([
            'form_id' => $form->getKey(),
            'key' => $data->key,
            'name' => $data->name,
            'order' => $data->order !== 0 ? $data->order : $defaultOrder,
        ]);

        foreach ($data->fields as $fieldOrder => $fieldData) {
            $this->createField($form, $group, $fieldData, $fieldOrder);
        }

        return $group;
    }

    private function createField(Form $form, Group $group, FieldDefinitionData $data, int $defaultOrder): Field
    {
        /** @var class-string<Field> $fieldModel */
        $fieldModel = config('forms.models.field', Field::class);

        /** @var Field $field */
        $field = $fieldModel::query()->create([
            'form_id' => $form->getKey(),
            'group_id' => $group->getKey(),
            'key' => $data->key,
            'name' => $data->name,
            'type' => $data->type,
            'help' => $data->help,
            'autofill' => $data->autofill,
            'options' => $data->options,
            'validations' => $data->validations,
            'order' => $data->order !== 0 ? $data->order : $defaultOrder,
        ]);

        return $field;
    }
}
