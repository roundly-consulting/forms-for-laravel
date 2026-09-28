<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Actions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionResult;
use RoundlyConsulting\Forms\Enums\SubmissionStatus;
use RoundlyConsulting\Forms\Events\FormSubmitted;
use RoundlyConsulting\Forms\Exceptions\DraftNotFoundException;
use RoundlyConsulting\Forms\Exceptions\FormSubmissionClosedException;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Resolvers\AttachesToSubmission;
use RoundlyConsulting\Forms\Support\FormSubmissionModel;
use RoundlyConsulting\Forms\Support\SubmissionModel;

/**
 * Promotes a draft submission to a final one, running full validation against
 * the values (and uploads) stored on the draft before it is finalized. Two
 * finalizes racing for one draft promote it once: the loser throws
 * DraftNotFoundException and fires nothing.
 */
final readonly class FinalizeSubmissionAction
{
    public function __construct(
        private ValidateSubmissionAction $validateSubmission,
    ) {}

    /**
     * @throws FormSubmissionClosedException when the draft's form has closed since it was
     *                                       saved, unless `$bypassClosed` — finalizing is a
     *                                       submission and obeys the same rule as submit().
     */
    public function execute(string $uuid, bool $bypassClosed = false): SubmissionResult
    {
        // `submissions.uuid` is a real uuid column, and a strict engine refuses to compare a
        // malformed string against one: on Postgres `where uuid = 'missing-uuid'` raises
        // `SQLSTATE[22P02] invalid input syntax for type uuid` from inside the query, before
        // the isEmpty() check below can turn "no rows" into DraftNotFoundException. SQLite
        // stores the column as text and compares anything, matching nothing, so the intended
        // exception fired there and the bug was invisible.
        //
        // A malformed uuid cannot identify a draft, so it is *not found* — the same answer
        // this method already gives for a well-formed uuid with no rows, and now the same
        // answer on every engine.
        if (! Str::isUuid($uuid)) {
            throw DraftNotFoundException::forUuid($uuid);
        }

        $submissionModel = SubmissionModel::class();

        /** @var Collection<int, Submission> $drafts */
        $drafts = $submissionModel::query()
            ->draft()
            ->where('uuid', $uuid)
            ->with(['form', 'group', 'field'])
            ->get();

        if ($drafts->isEmpty()) {
            throw DraftNotFoundException::forUuid($uuid);
        }

        /** @var Submission $first */
        $first = $drafts->first();
        $form = $first->form;

        if (! $bypassClosed) {
            $form->ensureAcceptingSubmissions();
        }

        $form->load(['groups.fields']);

        $uploads = [];
        $request = $this->requestFromDrafts($drafts, $uploads);

        try {
            $this->validateSubmission->execute($form, $request);
        } finally {
            foreach ($uploads as $upload) {
                if (is_file($upload->getPathname())) {
                    unlink($upload->getPathname());
                }
            }
        }

        $input = $request->all();

        $form->getConnection()->transaction(function () use ($drafts, $uuid, $input, $submissionModel): void {
            // Promote exactly the rows read above, and only while they are still drafts. A
            // concurrent finalize (or resume) that got there first leaves fewer of them to
            // promote: this one lost the race, rolls back and announces nothing.
            $promoted = $submissionModel::query()
                ->draft()
                ->where('uuid', $uuid)
                ->whereKey($drafts->modelKeys())
                ->update(['status' => SubmissionStatus::Final->value]);

            if ($promoted !== $drafts->count()) {
                throw DraftNotFoundException::forUuid($uuid);
            }

            $aggregateModel = FormSubmissionModel::class();

            $aggregateModel::query()
                ->where('uuid', $uuid)
                ->where('status', SubmissionStatus::Draft->value)
                ->update(['status' => SubmissionStatus::Final->value]);

            // A field its conditions hide was skipped by validation, so the value a draft
            // saved for it is unchecked: it has no answer, and none is kept.
            $drafts->each(function (Submission $submission) use ($input): void {
                if ($submission->field->trashed() || $submission->field->isVisible($input)) {
                    return;
                }

                $submission->update(['value' => ['value' => null]]);
                $submission->clearMediaBucket($submission->attachmentBucket());
            });
        });

        FormSubmitted::dispatch($form, $uuid, $drafts->count());

        return new SubmissionResult(
            uuid: $uuid,
            fieldCount: $drafts->count(),
            submittedAt: now(),
        );
    }

    /**
     * The request the draft would have been submitted with: each stored value at its field's
     * path, and each stored upload rebuilt as the file it was (see
     * {@see AttachesToSubmission::restoreUpload()}), collected in `$uploads` for cleanup.
     *
     * @param  Collection<int, Submission>  $drafts
     * @param  list<UploadedFile>  $uploads
     */
    private function requestFromDrafts(Collection $drafts, array &$uploads): Request
    {
        $payload = [];
        $files = [];

        $drafts->each(function (Submission $submission) use (&$payload, &$files, &$uploads): void {
            $resolver = $submission->field->resolver();

            if (! $resolver instanceof AttachesToSubmission) {
                data_set($payload, $submission->path(), $submission->value['value'] ?? null);

                return;
            }

            $upload = $resolver->restoreUpload($submission);

            if ($upload instanceof UploadedFile) {
                $uploads[] = $upload;
                data_set($files, $submission->path(), $upload);
            }
        });

        return Request::create('finalize', 'POST', $payload, files: $files);
    }
}
