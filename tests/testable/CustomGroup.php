<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Tests\testable;

use RoundlyConsulting\Forms\Models\Group;

/**
 * A host application's own subclass, configured through `forms.models`. It keeps the
 * packaged table, so every relation must still key off the packaged foreign key.
 */
final class CustomGroup extends Group
{
    protected $table = 'groups';
}
