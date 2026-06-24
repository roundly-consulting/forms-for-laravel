<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RoundlyConsulting\Forms\Models\Form;

class FormSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Form $form, public string $submission) {}
}
