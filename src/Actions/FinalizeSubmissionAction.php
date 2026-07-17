<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Actions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionResult;
use RoundlyConsulting\Forms\Enums\SubmissionStatus;
use RoundlyConsulting\Forms\Events\FormSubmitted;
use RoundlyConsulting\Forms\Exceptions\DraftNotFoundException;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Support\SubmissionModel;

/**
 * Promotes a draft submission to a final one, running full validation against
 * the values stored on the draft before it is finalized.
 */
final class FinalizeSubmissionAction
{
    public function __construct(
        private readonly ValidateSubmissionAction $validateSubmission = new ValidateSubmissionAction,
    ) {}

    public function execute(string $uuid): SubmissionResult
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
            ->with(['field.form', 'field.group'])
            ->get();

        if ($drafts->isEmpty()) {
            throw DraftNotFoundException::forUuid($uuid);
        }

        $first = $drafts->first();
        /** @var Submission $first */
        $form = $first->field->form;
        $form->load(['groups.fields']);

        $request = $this->requestFromDrafts($drafts);

        $this->validateSubmission->execute($form, $request);

        $form->getConnection()->transaction(function () use ($drafts, $first): void {
            $drafts->each(function (Submission $submission): void {
                $submission->update(['status' => SubmissionStatus::Final]);
            });

            $first->formSubmission?->update(['status' => SubmissionStatus::Final]);
        });

        FormSubmitted::dispatch($form, $uuid, $drafts->count());

        return new SubmissionResult(
            uuid: $uuid,
            fieldCount: $drafts->count(),
            submittedAt: now(),
        );
    }

    /** @param  Collection<int, Submission>  $drafts */
    private function requestFromDrafts($drafts): Request
    {
        $payload = [];

        $drafts->each(function (Submission $submission) use (&$payload): void {
            data_set($payload, $submission->path(), $submission->value['value'] ?? null);
        });

        return Request::create('finalize', parameters: $payload);
    }
}
