<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionData;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionResult;
use RoundlyConsulting\Forms\Enums\SubmissionStatus;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Submission;

/**
 * Saves a partial submission as a draft without running validation, so a
 * sender can resume and finalize it later.
 */
final class DraftSubmissionAction
{
    public function __construct(
        private readonly CreateSubmissionAction $createSubmission,
    ) {}

    public function execute(Form $form, Request $request, ?Model $sender = null, ?string $uuid = null): SubmissionResult
    {
        $resuming = $uuid !== null;
        $uuid ??= Str::orderedUuid()->toString();

        $fieldCount = $form->getConnection()->transaction(function () use ($form, $request, $sender, $uuid, $resuming): int {
            if ($resuming) {
                $this->clearExistingDraft($uuid);
            }

            return $form
                ->fields
                ->each(function (Field $field) use ($uuid, $request, $sender): void {
                    $value = $field->resolver()->toStorable(
                        request: $request,
                        sender: $sender,
                    );

                    $this->createSubmission->execute(
                        SubmissionData::forField($field, $uuid, $value, $sender, SubmissionStatus::Draft),
                    );
                })
                ->count();
        });

        return new SubmissionResult(
            uuid: $uuid,
            fieldCount: $fieldCount,
            submittedAt: now(),
        );
    }

    private function clearExistingDraft(string $uuid): void
    {
        /** @var class-string<Submission> $submissionModel */
        $submissionModel = config('forms.models.submission', Submission::class);

        $submissionModel::query()->draft()->where('uuid', $uuid)->forceDelete();
    }
}
