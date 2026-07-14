<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Support;

use RoundlyConsulting\Forms\Models\FormSubmission;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing submission aggregates from `forms.models.form_submission`.
 *
 * The toolkit's ModelResolver validates that the configured value is a real
 * Eloquent model; anything that is not a FormSubmission (so it cannot answer the
 * package's scopes and relations) falls back to the packaged model.
 */
final class FormSubmissionModel
{
    /** @return class-string<FormSubmission> */
    public static function class(): string
    {
        $model = ModelResolver::for('forms.models.form_submission', FormSubmission::class);

        return is_a($model, FormSubmission::class, true) ? $model : FormSubmission::class;
    }

    public static function new(): FormSubmission
    {
        $model = self::class();

        return new $model;
    }
}
