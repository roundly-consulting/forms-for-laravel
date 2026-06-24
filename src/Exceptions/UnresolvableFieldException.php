<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Exceptions;

final class UnresolvableFieldException extends FormsException
{
    public static function forType(string $type): self
    {
        return new self((string) trans('forms::messages.unresolvable_field', ['type' => $type]));
    }
}
