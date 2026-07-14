<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Support;

use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing per-field submission rows from `forms.models.submission`.
 *
 * The toolkit's ModelResolver validates that the configured value is a real
 * Eloquent model; anything that is not a Submission (so it cannot answer the
 * package's scopes and relations) falls back to the packaged model.
 */
final class SubmissionModel
{
    /** @return class-string<Submission> */
    public static function class(): string
    {
        $model = ModelResolver::for('forms.models.submission', Submission::class);

        return is_a($model, Submission::class, true) ? $model : Submission::class;
    }

    public static function new(): Submission
    {
        $model = self::class();

        return new $model;
    }
}
