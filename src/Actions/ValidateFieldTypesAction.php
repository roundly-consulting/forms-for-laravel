<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Actions;

use Illuminate\Http\Request;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Exceptions\InvalidAttributeValueException;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;
use RoundlyConsulting\Forms\Exceptions\InvalidFieldValueException;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;

/**
 * Validates each submitted value against the attributes-for-laravel type derived
 * from its field definition, catching type mismatches (a non-numeric value for a
 * `number` field, etc.) that Laravel's own field rules may not cover. Fields
 * whose type maps to a plain string are skipped — they keep the historical
 * free-form behaviour.
 *
 * @internal a building block of ValidateSubmissionAction; hosts go through `Forms::validate()`.
 */
final readonly class ValidateFieldTypesAction
{
    public function execute(Form $form, Request $request): void
    {
        $input = $request->all();

        $registry = new AttributeRegistry;

        $form->fields
            ->filter(fn (Field $field): bool => $field->attributeType() !== AttributeType::String_)
            ->filter(fn (Field $field): bool => $field->isVisible($input))
            ->each(function (Field $field) use ($registry, $input): void {
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
                } catch (InvalidAttributeValueException $exception) {
                    throw InvalidFieldValueException::forField($field, $exception);
                }
            });
    }
}
