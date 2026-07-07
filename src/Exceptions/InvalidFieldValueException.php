<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Exceptions;

use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Forms\Models\Field;
use Throwable;

/**
 * Raised when a submitted field value fails the type check derived from the
 * field's mapped attributes {@see AttributeType}.
 * Wraps the underlying attributes validation failure.
 */
final class InvalidFieldValueException extends FormsException
{
    public static function forField(Field $field, ?Throwable $previous = null): self
    {
        return new self(
            (string) trans('forms::messages.invalid_field_value', [
                'field' => $field->key,
                'type' => $field->attributeType()->value,
            ]),
            previous: $previous,
        );
    }
}
