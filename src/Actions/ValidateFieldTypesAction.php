<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Actions;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Exceptions\InvalidAttributeValueException;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;

/**
 * Validates each submitted value against the attributes-for-laravel type derived
 * from its field definition, catching type mismatches (a non-numeric value for a
 * `number` field, a decimal where it maps to `integer`, etc.) that Laravel's own field
 * rules may not cover. Fields whose type maps to a plain string are skipped — they keep
 * the historical free-form behaviour.
 *
 * A mismatch is the sender's mistake, so it fails like any other rule: one
 * {@see ValidationException} listing every mismatched field under its path.
 *
 * @internal a building block of ValidateSubmissionAction; hosts go through `Forms::validate()`.
 */
final readonly class ValidateFieldTypesAction
{
    /**
     * @throws ValidationException when a visible, non-null value fails its field's type
     */
    public function execute(Form $form, Request $request): void
    {
        $input = $request->all();

        $registry = new AttributeRegistry;

        /** @var array<string, list<string>> $errors */
        $errors = [];

        $form->fields
            ->filter(fn (Field $field): bool => $field->attributeType() !== AttributeType::String_)
            ->filter(fn (Field $field): bool => $field->isVisible($input))
            ->each(function (Field $field) use ($registry, $input, &$errors): void {
                /** @var mixed $value */
                $value = data_get($input, $field->path());

                if ($value === null) {
                    return;
                }

                $registry->define(new AttributeDefinitionData(
                    name: $field->path(),
                    type: $field->attributeType(),
                ));

                try {
                    $registry->validate($field->path(), $value);
                } catch (InvalidAttributeValueException) {
                    $errors[$field->path()] = [(string) trans('forms::messages.invalid_field_value', [
                        'attribute' => $field->name,
                        'type' => $field->attributeType()->value,
                    ])];
                }
            });

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
