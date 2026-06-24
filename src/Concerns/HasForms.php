<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionResult;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Submission;

/**
 * Add to any user/sender model to expose its form submissions and a shortcut
 * for submitting to a form as that sender.
 *
 * @phpstan-require-extends Model
 */
trait HasForms
{
    /** @return MorphMany<Submission, $this> */
    public function formSubmissions(): MorphMany
    {
        /** @var class-string<Submission> $submission */
        $submission = config('forms.models.submission', Submission::class);

        return $this->morphMany($submission, 'sender');
    }

    public function submitTo(Form $form, Request $request, bool $bypassClosed = false): SubmissionResult
    {
        return Forms::submit($form, $request, $this, $bypassClosed);
    }

    public function draftTo(Form $form, Request $request, ?string $uuid = null): SubmissionResult
    {
        return Forms::draft($form, $request, $this, $uuid);
    }
}
