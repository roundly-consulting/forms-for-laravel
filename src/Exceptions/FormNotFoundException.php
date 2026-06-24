<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Exceptions;

final class FormNotFoundException extends FormsException
{
    public static function forKey(string $key): self
    {
        return new self((string) trans('forms::messages.form_not_found', ['key' => $key]));
    }
}
