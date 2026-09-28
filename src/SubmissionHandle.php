<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms;

use RoundlyConsulting\Forms\DataTransferObjects\AssembledSubmission;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionResult;
use RoundlyConsulting\Forms\Exceptions\DraftNotFoundException;
use RoundlyConsulting\Forms\Exceptions\SubmissionNotFoundException;
use RoundlyConsulting\Forms\Models\FormSubmission;

/**
 * `Forms::submission($uuid)` — one whole submission (or draft), addressed by the uuid
 * `submit()` / `draft()` returned or by its FormSubmission model. Every call goes through
 * the manager, so host overrides and `Forms::fake()` see it.
 */
final class SubmissionHandle
{
    private ?FormSubmission $model;

    private readonly string $uuid;

    /**
     * @internal build it with `Forms::submission($uuid)`
     */
    public function __construct(
        private readonly FormsManager $forms,
        string|FormSubmission $submission,
    ) {
        $this->model = $submission instanceof FormSubmission ? $submission : null;
        $this->uuid = $submission instanceof FormSubmission ? $submission->uuid : $submission;
    }

    public function uuid(): string
    {
        return $this->uuid;
    }

    /**
     * The FormSubmission aggregate: status, sender, form and approval state.
     *
     * @throws SubmissionNotFoundException
     */
    public function model(): FormSubmission
    {
        return $this->model ??= $this->forms->findSubmission($this->uuid);
    }

    /**
     * The submitted values, keyed by field — a draft's included.
     *
     * @throws SubmissionNotFoundException
     */
    public function get(): AssembledSubmission
    {
        return $this->forms->submissions($this->model()->form)
            ->withDrafts()
            ->whereUuid($this->uuid)
            ->first() ?? throw SubmissionNotFoundException::forUuid($this->uuid);
    }

    /**
     * Validate a draft's stored values and promote it to a final submission.
     *
     * @throws DraftNotFoundException when the submission is not a draft
     */
    public function finalize(bool $bypassClosed = false): SubmissionResult
    {
        return $this->forms->finalize($this->uuid, $bypassClosed);
    }

    /**
     * Open a fluent review over this submission (requires `forms.approvals.enabled`).
     *
     * @throws SubmissionNotFoundException
     */
    public function review(): PendingSubmissionReview
    {
        return $this->forms->review($this->model());
    }
}
