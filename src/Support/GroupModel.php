<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Support;

use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing field groups from `forms.models.group`.
 *
 * The toolkit's ModelResolver validates that the configured value is a real
 * Eloquent model; anything that is not a Group (so it cannot answer the
 * package's scopes and relations) falls back to the packaged model.
 */
final class GroupModel
{
    /** @return class-string<Group> */
    public static function class(): string
    {
        $model = ModelResolver::for('forms.models.group', Group::class);

        return is_a($model, Group::class, true) ? $model : Group::class;
    }

    public static function new(): Group
    {
        $model = self::class();

        return new $model;
    }
}
