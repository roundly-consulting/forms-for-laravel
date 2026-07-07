<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Exceptions;

final class ReviewsDisabledException extends FormsException
{
    public static function make(): self
    {
        return new self((string) trans('forms::messages.reviews_disabled'));
    }
}
