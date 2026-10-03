<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Support;

use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing per-field submission rows from `forms.models.submission`.
 *
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
 */
final class SubmissionModel
{
    /** @return class-string<Submission> */
    public static function class(): string
    {
        return ModelResolver::for('forms.models.submission', Submission::class);
    }

    public static function new(): Submission
    {
        $model = self::class();

        return new $model;
    }
}
