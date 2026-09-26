<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionData;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionResult;
use RoundlyConsulting\Forms\Enums\SubmissionStatus;
use RoundlyConsulting\Forms\Exceptions\DraftNotFoundException;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Resolvers\AttachesToSubmission;
use RoundlyConsulting\Forms\Support\FormSubmissionModel;
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
                $this->guardResumable($form, $uuid);
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

    /**
     * Only a draft of this form can be resumed. Resuming clears the uuid's *draft* rows and
     * rewrites its aggregate, so a finalized uuid (or another form's draft) would be reopened
     * next to its final rows — and finalizing it again doubled every field's row. A malformed
     * uuid identifies nothing (and a strict engine rejects it in the query), so it is not
     * found either — the same answers `finalize()` gives.
     */
    private function guardResumable(Form $form, string $uuid): void
    {
        if (! Str::isUuid($uuid)) {
            throw DraftNotFoundException::forUuid($uuid);
        }

        $aggregateModel = FormSubmissionModel::class();
        $aggregate = $aggregateModel::query()->where('uuid', $uuid)->first();

        $foreignAggregate = $aggregate !== null
            && (! $aggregate->isDraft() || (string) $aggregate->form_id !== (string) $form->getKey());

        $submissionModel = SubmissionModel::class();

        $foreignRows = $submissionModel::query()
            ->where('uuid', $uuid)
            ->where(fn ($query) => $query
                ->where('form_id', '!=', $form->getKey())
                ->orWhereNull('status')
                ->orWhere('status', '!=', SubmissionStatus::Draft->value))
            ->exists();

        if ($foreignAggregate || $foreignRows) {
            throw DraftNotFoundException::forUuid($uuid);
        }
    }

    private function clearExistingDraft(string $uuid): void
    {
        $submissionModel = SubmissionModel::class();

        $submissionModel::query()->draft()->where('uuid', $uuid)->forceDelete();
    }
}
