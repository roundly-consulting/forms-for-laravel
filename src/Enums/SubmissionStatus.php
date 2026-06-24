<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Enums;

enum SubmissionStatus: string
{
    case Draft = 'draft';
    case Final = 'final';
}
