<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Forms\Enums\SubmissionStatus;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\FormSubmission;
use RoundlyConsulting\Forms\Support\FormSubmissionModel;

/**
 * Creates (or resumes) the {@see FormSubmission} aggregate that groups the
 * per-field submission rows sharing a `uuid` and carries the submission's
 * lifecycle status.
 */
final class CreateFormSubmissionAction
{
    public function execute(Form $form, string $uuid, ?Model $sender = null, SubmissionStatus $status = SubmissionStatus::Final): FormSubmission
    {
        $model = FormSubmissionModel::class();

        $senderKey = $sender?->getKey();

        /** @var FormSubmission $submission */
        $submission = $model::query()->updateOrCreate(
            ['uuid' => $uuid],
            [
                'form_id' => $form->getKey(),
                'sender_id' => $senderKey === null ? null : (int) $senderKey,
                'sender_type' => $sender?->getMorphClass(),
                'status' => $status,
            ],
        );

        return $submission;
    }
}
