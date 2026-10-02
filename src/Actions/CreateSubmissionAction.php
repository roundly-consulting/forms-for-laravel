<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Actions;

use Illuminate\Support\Str;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionData;
use RoundlyConsulting\Forms\Enums\SubmissionStatus;
use RoundlyConsulting\Forms\Exceptions\SubmissionNotFoundException;
use RoundlyConsulting\Forms\Models\FormSubmission;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Support\FormSubmissionModel;
use RoundlyConsulting\Forms\Support\SubmissionModel;

/**
 * Writes one per-field submission row. `submit()` / `draft()` hand it the aggregate the row
 * belongs to; a row written directly (`Forms::createSubmission()` — imports, seeds) names
 * none, so it is filed under its uuid's aggregate, which the first such row creates as a
 * final submission. Rows sharing a uuid therefore read, finalize and review as one
 * submission, like every other.
 */
final readonly class CreateSubmissionAction
{
    /**
     * @throws SubmissionNotFoundException when a directly written row's uuid is malformed, or
     *                                     names another form's submission or a draft
     */
    public function execute(SubmissionData $data): Submission
    {
        $model = SubmissionModel::class();

        return $model::query()->getConnection()->transaction(function () use ($data, $model): Submission {
            $submission = new $model([
                'uuid' => $data->uuid,
                'form_submission_id' => $data->formSubmissionId ?? $this->aggregateFor($data)->getKey(),
                'sender_id' => $data->senderId,
                'sender_type' => $data->senderType,
                'form_id' => $data->formId,
                'group_id' => $data->groupId,
                'field_id' => $data->fieldId,
                'value' => $data->value,
                'status' => $data->status,
            ]);

            $submission->save();

            return $submission;
        });
    }

    /**
     * The final submission a directly written row joins. Never another form's submission, and
     * never a draft — a row written here carries no draft status, so it would read as final
     * inside an unfinished submission.
     */
    private function aggregateFor(SubmissionData $data): FormSubmission
    {
        if (! Str::isUuid($data->uuid)) {
            throw SubmissionNotFoundException::forUuid($data->uuid);
        }

        $aggregateModel = FormSubmissionModel::class();

        /** @var FormSubmission $aggregate */
        $aggregate = $aggregateModel::query()->createOrFirst(['uuid' => $data->uuid], [
            'form_id' => $data->formId,
            'sender_id' => $data->senderId,
            'sender_type' => $data->senderType,
            'status' => SubmissionStatus::Final,
        ]);

        if ((string) $aggregate->form_id !== (string) $data->formId || $aggregate->isDraft()) {
            throw SubmissionNotFoundException::forUuid($data->uuid);
        }

        return $aggregate;
    }
}
