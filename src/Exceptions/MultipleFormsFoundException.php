<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Exceptions;

final class MultipleFormsFoundException extends FormsException
{
    public static function forKey(string $key): self
    {
        return new self((string) trans('forms::messages.multiple_forms_found', ['key' => $key]));
    }
}
