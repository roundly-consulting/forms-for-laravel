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
use RoundlyConsulting\Forms\Exceptions\FormSubmissionClosedException;
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
final readonly class DraftSubmissionAction
{
    public function __construct(
        private CreateSubmissionAction $createSubmission,
        private CreateFormSubmissionAction $createFormSubmission,
    ) {}

    /**
     * @throws FormSubmissionClosedException when the form is closed, unless `$bypassClosed`:
     *                                       a draft is a submission in progress, so it obeys
     *                                       the same rule as submit().
     */
    public function execute(Form $form, Request $request, ?Model $sender = null, ?string $uuid = null, bool $bypassClosed = false): SubmissionResult
    {
        if (! $bypassClosed) {
            $form->ensureAcceptingSubmissions();
        }

        $resuming = $uuid !== null;
        $uuid ??= Str::orderedUuid()->toString();

        $fieldCount = $form->getConnection()->transaction(function () use ($form, $request, $sender, $uuid, $resuming): int {
            if ($resuming) {
                $this->guardResumable($form, $uuid, $sender);
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
     * Only a draft of this form, saved by this sender, can be resumed. Resuming clears the
     * uuid's *draft* rows and rewrites its aggregate, so a finalized uuid (or another form's
     * draft) would be reopened next to its final rows — and finalizing it again doubled every
     * field's row; and another sender's draft would be taken over, its values replaced and
     * its sender rewritten. An anonymous draft stays anonymous: a signed-in sender cannot
     * claim it. A malformed uuid identifies nothing (and a strict engine rejects it in the
     * query), so it is not found either — the same answers `finalize()` gives, which never
     * confirm that someone else's draft exists.
     */
    private function guardResumable(Form $form, string $uuid, ?Model $sender): void
    {
        if (! Str::isUuid($uuid)) {
            throw DraftNotFoundException::forUuid($uuid);
        }

        $aggregateModel = FormSubmissionModel::class();
        $aggregate = $aggregateModel::query()->where('uuid', $uuid)->first();

        $foreignAggregate = $aggregate !== null && (
            ! $aggregate->isDraft()
            || (string) $aggregate->form_id !== (string) $form->getKey()
            || ! $this->sentBy($aggregate->sender_type, $aggregate->sender_id, $sender)
        );

        $submissionModel = SubmissionModel::class();

        $rows = $submissionModel::query()
            ->where('uuid', $uuid)
            ->get(['form_id', 'status', 'sender_type', 'sender_id']);

        $foreignRows = $rows->contains(fn (Submission $row): bool => (string) $row->form_id !== (string) $form->getKey()
            || $row->status !== SubmissionStatus::Draft
            || ! $this->sentBy($row->sender_type, $row->sender_id, $sender));

        if ($foreignAggregate || $foreignRows) {
            throw DraftNotFoundException::forUuid($uuid);
        }
    }

    private function sentBy(?string $senderType, int|string|null $senderId, ?Model $sender): bool
    {
        $key = SubmissionData::senderKey($sender);

        return $senderType === $sender?->getMorphClass()
            && ($senderId === null ? $key === null : $key !== null && (string) $senderId === (string) $key);
    }

    /**
     * Force-delete the draft's rows one model at a time, so each purges the uploads it owns
     * (a query-level delete skips the model events that do it, orphaning the files).
     */
    private function clearExistingDraft(string $uuid): void
    {
        $submissionModel = SubmissionModel::class();

        $submissionModel::query()
            ->draft()
            ->where('uuid', $uuid)
            ->get()
            ->each(fn (Submission $submission): ?bool => $submission->forceDelete());
    }
}
