<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Exceptions;

final class FormSubmissionClosedException extends FormsException
{
    public static function forKey(string $key): self
    {
        return new self((string) trans('forms::messages.submission_closed', ['key' => $key]));
    }
}
