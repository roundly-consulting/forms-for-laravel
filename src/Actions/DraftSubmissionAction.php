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
use RoundlyConsulting\Forms\Resolvers\AttachesToSubmission;
use RoundlyConsulting\Forms\Support\SubmissionModel;

/**
 * Saves a partial submission as a draft without running validation, so a
 * sender can resume and finalize it later.
 */
final class DraftSubmissionAction
{
    public function __construct(
        private readonly CreateSubmissionAction $createSubmission = new CreateSubmissionAction,
        private readonly CreateFormSubmissionAction $createFormSubmission = new CreateFormSubmissionAction,
    ) {}

    public function execute(Form $form, Request $request, ?Model $sender = null, ?string $uuid = null): SubmissionResult
    {
        $resuming = $uuid !== null;
        $uuid ??= Str::orderedUuid()->toString();

        $fieldCount = $form->getConnection()->transaction(function () use ($form, $request, $sender, $uuid, $resuming): int {
            if ($resuming) {
                $this->clearExistingDraft($uuid);
            }

            $aggregate = $this->createFormSubmission->execute($form, $uuid, $sender, SubmissionStatus::Draft);

            return $form
                ->fields
                ->each(function (Field $field) use ($aggregate, $uuid, $request, $sender): void {
                    $resolver = $field->resolver();

                    $submission = $this->createSubmission->execute(
                        SubmissionData::forField(
                            $field,
                            $uuid,
                            $resolver->toStorable($request, $sender),
                            $sender,
                            SubmissionStatus::Draft,
                            $aggregate->getKey(),
                        ),
                    );

                    if ($resolver instanceof AttachesToSubmission) {
                        $resolver->attach($submission, $request, $sender);
                    }
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
        $submissionModel = SubmissionModel::class();

        $submissionModel::query()->draft()->where('uuid', $uuid)->forceDelete();
    }
}
