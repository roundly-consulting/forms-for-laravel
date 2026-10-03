<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Support;

use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing field groups from `forms.models.group`.
 *
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
 */
final class GroupModel
{
    /** @return class-string<Group> */
    public static function class(): string
    {
        return ModelResolver::for('forms.models.group', Group::class);
    }

    public static function new(): Group
    {
        $model = self::class();

        return new $model;
    }
}
