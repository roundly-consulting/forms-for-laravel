<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Exceptions;

final class SubmissionNotFoundException extends FormsException
{
    public static function forUuid(string $uuid): self
    {
        return new self((string) trans('forms::messages.submission_not_found', ['uuid' => $uuid]));
    }
}
