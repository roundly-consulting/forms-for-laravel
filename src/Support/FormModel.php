<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Support;

use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing form definitions from `forms.models.form`.
 *
 * The toolkit's ModelResolver validates that the configured value is a real
 * Eloquent model; anything that is not a Form (so it cannot answer the
 * package's scopes and relations) falls back to the packaged model.
 */
final class FormModel
{
    /** @return class-string<Form> */
    public static function class(): string
    {
        $model = ModelResolver::for('forms.models.form', Form::class);

        return is_a($model, Form::class, true) ? $model : Form::class;
    }

    public static function new(): Form
    {
        $model = self::class();

        return new $model;
    }
}
