<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Exceptions;

final class DraftNotFoundException extends FormsException
{
    public static function forUuid(string $uuid): self
    {
        return new self((string) trans('forms::messages.draft_not_found', ['uuid' => $uuid]));
    }
}
