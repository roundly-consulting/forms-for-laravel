<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Tests\testable;

use RoundlyConsulting\Forms\Models\FormSubmission;

/**
 * A host application's own subclass, configured through `forms.models`. It keeps the
 * packaged table, so every relation must still key off the packaged foreign key.
 */
final class CustomFormSubmission extends FormSubmission
{
    protected $table = 'form_submissions';
}
