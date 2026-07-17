<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Tests\testable;

use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Testing\Fixtures\Concerns\CountsCreations;

/**
 * A host application's own subclass, configured through `forms.models`. It keeps the
 * packaged table, so every relation must still key off the packaged foreign key.
 */
final class CustomField extends Field
{
    /**
     * Counting `created` events on this exact class is the independent oracle a swap
     * really took effect. Without it `toHonourModelSwap` silently downgrades to an
     * `instanceof` check, which a row created as the *packaged* class can still pass.
     */
    use CountsCreations;

    protected $table = 'fields';
}
