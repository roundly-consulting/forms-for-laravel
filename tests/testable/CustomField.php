<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Tests\testable;

use RoundlyConsulting\Forms\Models\Field;

/**
 * A host application's own subclass, configured through `forms.models`. It keeps the
 * packaged table, so every relation must still key off the packaged foreign key.
 */
final class CustomField extends Field
{
    protected $table = 'fields';
}
