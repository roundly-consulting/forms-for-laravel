<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
use RoundlyConsulting\Forms\Actions\CreateFormAction;
use RoundlyConsulting\Forms\Actions\CreateSubmissionAction;
use RoundlyConsulting\Forms\Actions\DraftSubmissionAction;
use RoundlyConsulting\Forms\Actions\FinalizeSubmissionAction;
use RoundlyConsulting\Forms\Actions\FindFormAction;
use RoundlyConsulting\Forms\Actions\FindSubmissionAction;
use RoundlyConsulting\Forms\Actions\ReviewSubmissionAction;
use RoundlyConsulting\Forms\Actions\StoreSubmissionAction;
use RoundlyConsulting\Forms\Actions\SyncFormsAction;
use RoundlyConsulting\Forms\Actions\UpdateFormAction;
use RoundlyConsulting\Forms\Actions\ValidateSubmissionAction;
use RoundlyConsulting\Forms\DataTransferObjects\FormDefinitionData;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionData;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionResult;
use RoundlyConsulting\Forms\Exceptions\SubmissionNotFoundException;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\FormSubmission;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Submissions\SubmissionQuery;

/**
 * The root of the `Forms` facade. Holds no logic of its own — every operation resolves
 * its action from the container, so host overrides and `Forms::fake()` apply.
 *
 * Deliberately not `final`: `Testing\FormsFake` extends it, so a constructor-injected
 * manager keeps working under `Forms::fake()`.
 */
class FormsManager
{
    public function __construct(
        protected readonly Container $container,
    ) {}

    public function find(string $key): Form
    {
        return $this->container->make(FindFormAction::class)->execute($key);
    }

    /**
     * Start a fluent form definition; `create()` stores it.
     */
    public function define(string $key, string $name): FormBuilder
    {
        return new FormBuilder($this, $key, $name);
    }

    public function create(FormDefinitionData $data): Form
    {
        return $this->container->make(CreateFormAction::class)->execute($data);
    }

    /**
     * Start a fluent edit of an existing form; `save()` diffs it against the stored one.
     */
    public function update(string $key): UpdateFormBuilder
    {
        return new UpdateFormBuilder($this, $this->find($key));
    }

    /**
     * Create or update every form in `$definitions` (default: `forms.definitions`).
     *
     * @param  list<array<string, mixed>>|null  $definitions
     * @return list<string> the synced form keys
     */
    public function sync(?array $definitions = null): array
    {
        return $this->container->make(SyncFormsAction::class)->execute($definitions);
    }

    /** @return array<string, mixed> */
    public function validate(Form $form, Request $request): array
    {
        return $this->container->make(ValidateSubmissionAction::class)->execute($form, $request);
    }

    public function submit(Form $form, Request $request, ?Model $sender = null, bool $bypassClosed = false): SubmissionResult
    {
        return $this->container->make(StoreSubmissionAction::class)->execute($form, $request, $sender, $bypassClosed);
    }

    public function draft(Form $form, Request $request, ?Model $sender = null, ?string $uuid = null, bool $bypassClosed = false): SubmissionResult
    {
        return $this->container->make(DraftSubmissionAction::class)->execute($form, $request, $sender, $uuid, $bypassClosed);
    }

    public function finalize(string $uuid, bool $bypassClosed = false): SubmissionResult
    {
        return $this->container->make(FinalizeSubmissionAction::class)->execute($uuid, $bypassClosed);
    }

    /**
     * Read a form's submissions, assembled per uuid.
     */
    public function submissions(Form $form): SubmissionQuery
    {
        return new SubmissionQuery($form);
    }

    /**
     * One submission (or draft) by the uuid `submit()` / `draft()` returned, or its model:
     * read it, finalize it, or open a review over it.
     */
    public function submission(string|FormSubmission $submission): SubmissionHandle
    {
        return new SubmissionHandle($this, $submission);
    }

    /**
     * Open a fluent review over a whole submission, routed through the approvals
     * engine. Requires `forms.approvals.enabled`.
     *
     * @throws SubmissionNotFoundException when a uuid names no submission
     */
    public function review(string|FormSubmission $submission): PendingSubmissionReview
    {
        return new PendingSubmissionReview(
            $this,
            $submission instanceof FormSubmission ? $submission : $this->findSubmission($submission),
        );
    }

    /**
     * Write a single field row directly. The row carries no draft status, so it reads as a
     * final submission — it obeys the closed-form rule unless `$bypassClosed` (imports, seeds).
     * Rows sharing a `$uuid` are filed under one submission, created by the first of them,
     * which `submission($uuid)` reads and `review($uuid)` reviews like any other.
     *
     * @param  array<array-key, mixed>  $value
     *
     * @throws SubmissionNotFoundException when `$uuid` is malformed, or names another form's
     *                                     submission or a draft
     */
    public function createSubmission(Field $field, array $value, ?Model $sender = null, ?string $uuid = null, bool $bypassClosed = false): Submission
    {
        if (! $bypassClosed) {
            $field->form->ensureAcceptingSubmissions();
        }

        return $this->container->make(CreateSubmissionAction::class)->execute(
            SubmissionData::forField($field, $uuid ?: Str::orderedUuid()->toString(), $value, $sender),
        );
    }

    /*
     * The operations behind the builders and handles. They are public only so those can
     * reach them, and @internal so the facade never documents them. They are also where
     * FormsFake records: every call — through the facade, an injected manager, a builder,
     * a handle or the HasForms trait — lands on this manager.
     */

    /**
     * @internal the body of `update($key)->…->save()`
     */
    public function updateFrom(FormDefinitionData $data): Form
    {
        return $this->container->make(UpdateFormAction::class)->execute($data);
    }

    /**
     * @internal the body of `submission($uuid)->model()` and `review($uuid)`
     *
     * @throws SubmissionNotFoundException
     */
    public function findSubmission(string $uuid): FormSubmission
    {
        return $this->container->make(FindSubmissionAction::class)->execute($uuid);
    }

    /**
     * @internal the body of `review($submission)->…->open()`
     *
     * @param  list<Model>  $approvers
     */
    public function openReview(FormSubmission $submission, array $approvers, ApprovalRule $rule = ApprovalRule::Unanimous, ?int $quorum = null): ApprovalRequest
    {
        return $this->container->make(ReviewSubmissionAction::class)->execute($submission, $approvers, $rule, $quorum);
    }
}
