<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Support;

use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing fields from `forms.models.field`.
 *
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
 */
final class FieldModel
{
    /** @return class-string<Field> */
    public static function class(): string
    {
        return ModelResolver::for('forms.models.field', Field::class);
    }

    public static function new(): Field
    {
        $model = self::class();

        return new $model;
    }
}
