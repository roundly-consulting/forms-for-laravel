<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Support;

use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing fields from `forms.models.field`.
 *
 * The toolkit's ModelResolver validates that the configured value is a real
 * Eloquent model; anything that is not a Field (so it cannot answer the
 * package's scopes and relations) falls back to the packaged model.
 */
final class FieldModel
{
    /** @return class-string<Field> */
    public static function class(): string
    {
        $model = ModelResolver::for('forms.models.field', Field::class);

        return is_a($model, Field::class, true) ? $model : Field::class;
    }

    public static function new(): Field
    {
        $model = self::class();

        return new $model;
    }
}
