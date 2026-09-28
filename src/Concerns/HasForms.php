<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionResult;
use RoundlyConsulting\Forms\FormsManager;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Support\SubmissionModel;

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
        return $this->morphMany(SubmissionModel::class(), 'sender');
    }

    public function submitTo(Form $form, Request $request, bool $bypassClosed = false): SubmissionResult
    {
        return app(FormsManager::class)->submit($form, $request, $this, $bypassClosed);
    }

    public function draftTo(Form $form, Request $request, ?string $uuid = null, bool $bypassClosed = false): SubmissionResult
    {
        return app(FormsManager::class)->draft($form, $request, $this, $uuid, $bypassClosed);
    }
}
