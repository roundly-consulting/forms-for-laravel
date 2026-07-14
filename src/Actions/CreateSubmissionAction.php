<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Actions;

use RoundlyConsulting\Forms\DataTransferObjects\SubmissionData;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Support\SubmissionModel;

final class CreateSubmissionAction
{
    public function execute(SubmissionData $data): Submission
    {
        $model = SubmissionModel::class();

        $submission = new $model([
            'uuid' => $data->uuid,
            'form_submission_id' => $data->formSubmissionId,
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
    }
}
