<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Tests\testable;

/**
 * A class an `autofill` column could name that is not an Autofill: building it is a side
 * effect of its own, which reading the field must never trigger.
 */
class NotAnAutofill
{
    public static int $built = 0;

    public function __construct()
    {
        self::$built++;
    }
}
