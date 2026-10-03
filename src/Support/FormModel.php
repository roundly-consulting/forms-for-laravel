<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Support;

use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing form definitions from `forms.models.form`.
 *
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
 */
final class FormModel
{
    /** @return class-string<Form> */
    public static function class(): string
    {
        return ModelResolver::for('forms.models.form', Form::class);
    }

    public static function new(): Form
    {
        $model = self::class();

        return new $model;
    }
}
