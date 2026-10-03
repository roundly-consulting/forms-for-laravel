<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Support;

use RoundlyConsulting\Forms\Models\FormSubmission;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing submission aggregates from `forms.models.form_submission`.
 *
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
 */
final class FormSubmissionModel
{
    /** @return class-string<FormSubmission> */
    public static function class(): string
    {
        return ModelResolver::for('forms.models.form_submission', FormSubmission::class);
    }

    public static function new(): FormSubmission
    {
        $model = self::class();

        return new $model;
    }
}
