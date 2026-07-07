<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;

final class ValidateSubmissionAction
{
    public function __construct(
        private readonly ValidateFieldTypesAction $validateFieldTypes = new ValidateFieldTypesAction,
    ) {}

    /**
     * Build a validator from each field's rules and return the validated data.
     *
     * Fields hidden by their conditions are skipped entirely. A field whose
     * condition is met keeps its rules (including a conditional `required`).
     * After Laravel's own rules pass, each submitted value is checked against
     * the attributes type derived from its field definition.
     *
     * @return array<string, mixed>
     */
    public function execute(Form $form, Request $request): array
    {
        $input = $request->all();

        /** @var Collection<int, Field> $fieldsToValidate */
        $fieldsToValidate = $form
            ->fields
            ->filter(fn (Field $field): bool => ! is_null($field->validations))
            ->filter(fn (Field $field): bool => $field->isVisible($input));

        $validator = validator(
            data: $input,
            rules: $fieldsToValidate->mapWithKeys(fn (Field $field): array => [
                $field->path() => $field->validations,
            ])->all(),
            messages: $this->messages($fieldsToValidate),
            attributes: $this->attributes($fieldsToValidate),
        );

        /** @var array<string, mixed> $validated */
        $validated = $validator->validate();

        $this->validateFieldTypes->execute($form, $request);

        return $validated;
    }

    /**
     * @param  Collection<int, Field>  $fields
     * @return array<string, string>
     */
    private function messages(Collection $fields): array
    {
        return $fields
            ->reject(fn (Field $field): bool => blank($field->messages))
            ->mapWithKeys(function (Field $field): array {
                $path = $field->path();
                $messages = [];

                foreach ($field->messages ?: [] as $rule => $message) {
                    $messages["{$path}.{$rule}"] = (string) $message;
                }

                return $messages;
            })
            ->all();
    }

    /**
     * @param  Collection<int, Field>  $fields
     * @return array<string, string>
     */
    private function attributes(Collection $fields): array
    {
        return $fields->mapWithKeys(fn (Field $field): array => [
            $field->path() => $field->name,
        ])->all();
    }
}
