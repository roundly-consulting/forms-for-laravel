<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Actions;

use Illuminate\Http\Request;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;

final class ValidateSubmissionAction
{
    /**
     * Build a validator from each field's rules and return the validated data.
     *
     * @return array<string, mixed>
     */
    public function execute(Form $form, Request $request): array
    {
        $fieldsToValidate = $form
            ->fields
            ->filter(fn (Field $field): bool => ! is_null($field->validations));

        $validator = validator(
            data: $request->all(),
            rules: $fieldsToValidate->mapWithKeys(fn (Field $field): array => [
                $field->path() => $field->validations,
            ])->toArray(),
            attributes: $fieldsToValidate->mapWithKeys(fn (Field $field): array => [
                $field->path() => $field->name,
            ])->toArray(),
        );

        /** @var array<string, mixed> $validated */
        $validated = $validator->validate();

        return $validated;
    }
}
