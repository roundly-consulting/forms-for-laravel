<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Tests\testable;

use RoundlyConsulting\Forms\Autofill\Autofill;
use RoundlyConsulting\Forms\Models\Field;

class CustomAutofill implements Autofill
{
    public function fill(Field $field): mixed
    {
        return "{$field->name} Autofill";
    }
}
