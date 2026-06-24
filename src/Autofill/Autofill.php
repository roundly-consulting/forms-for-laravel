<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Autofill;

use RoundlyConsulting\Forms\Models\Field;

interface Autofill
{
    public function fill(Field $field): mixed;
}
