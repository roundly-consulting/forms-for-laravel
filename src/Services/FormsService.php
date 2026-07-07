<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use RoundlyConsulting\Forms\Actions\CreateFormAction;
use RoundlyConsulting\Forms\Actions\CreateSubmissionAction;
use RoundlyConsulting\Forms\Actions\DraftSubmissionAction;
use RoundlyConsulting\Forms\Actions\FinalizeSubmissionAction;
use RoundlyConsulting\Forms\Actions\FindFormAction;
use RoundlyConsulting\Forms\Actions\StoreSubmissionAction;
use RoundlyConsulting\Forms\Actions\SyncFormsAction;
use RoundlyConsulting\Forms\Actions\UpdateFormAction;
use RoundlyConsulting\Forms\Actions\ValidateSubmissionAction;
use RoundlyConsulting\Forms\DataTransferObjects\FormDefinitionData;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionData;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionResult;
use RoundlyConsulting\Forms\FormBuilder;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\FormSubmission;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\PendingSubmissionReview;
use RoundlyConsulting\Forms\Submissions\SubmissionQuery;
use RoundlyConsulting\Forms\Testing\FormsFake;
use RoundlyConsulting\Forms\UpdateFormBuilder;

/**
 * Backward-compatible manager backing the `Forms` facade. Holds no logic of its
 * own — every method delegates to a dedicated Action.
 */
class FormsService
{
    public function __construct(
        private readonly FindFormAction $findForm = new FindFormAction,
        private readonly ValidateSubmissionAction $validateSubmission = new ValidateSubmissionAction,
        private readonly StoreSubmissionAction $storeSubmission = new StoreSubmissionAction(new CreateSubmissionAction),
        private readonly CreateSubmissionAction $createSubmissionAction = new CreateSubmissionAction,
        private readonly CreateFormAction $createForm = new CreateFormAction,
        private readonly UpdateFormAction $updateForm = new UpdateFormAction,
        private readonly DraftSubmissionAction $draftSubmission = new DraftSubmissionAction(new CreateSubmissionAction),
        private readonly FinalizeSubmissionAction $finalizeSubmission = new FinalizeSubmissionAction,
        private readonly SyncFormsAction $syncForms = new SyncFormsAction,
    ) {}

    /**
     * Swap the bound manager for a recording {@see FormsFake} and return it,
     * so host-application tests can assert on form activity.
     */
    public static function fake(): FormsFake
    {
        $fake = new FormsFake;

        app()->instance('forms.manager', $fake);
        Facade::clearResolvedInstance('forms.manager');

        return $fake;
    }

    public function find(string $key): Form
    {
        return $this->findForm->execute($key);
    }

    public function define(string $key, string $name): FormBuilder
    {
        return new FormBuilder($this->createForm, $key, $name);
    }

    public function create(FormDefinitionData $data): Form
    {
        return $this->createForm->execute($data);
    }

    public function update(string $key): UpdateFormBuilder
    {
        return new UpdateFormBuilder($this->updateForm, $this->find($key));
    }

    /** @param  list<array<string, mixed>>|null  $definitions
     * @return list<string>
     */
    public function sync(?array $definitions = null): array
    {
        return $this->syncForms->execute($definitions);
    }

    /** @return array<string, mixed> */
    public function validate(Form $form, Request $request): array
    {
        return $this->validateSubmission->execute($form, $request);
    }

    public function submit(Form $form, Request $request, ?Model $sender = null, bool $bypassClosed = false): SubmissionResult
    {
        return $this->storeSubmission->execute($form, $request, $sender, $bypassClosed);
    }

    public function storeSubmission(Form $form, Request $request, ?Model $sender = null, bool $bypassClosed = false): string
    {
        return $this->submit($form, $request, $sender, $bypassClosed)->uuid;
    }

    public function draft(Form $form, Request $request, ?Model $sender = null, ?string $uuid = null): SubmissionResult
    {
        return $this->draftSubmission->execute($form, $request, $sender, $uuid);
    }

    public function finalize(string $uuid): SubmissionResult
    {
        return $this->finalizeSubmission->execute($uuid);
    }

    public function submissions(Form $form): SubmissionQuery
    {
        return new SubmissionQuery($form);
    }

    /**
     * Open a fluent review over a whole submission, routed through the approvals
     * engine. Requires `forms.approvals.enabled`.
     */
    public function review(FormSubmission $submission): PendingSubmissionReview
    {
        return new PendingSubmissionReview($submission);
    }

    /** @param  array<array-key, mixed>  $value */
    public function createSubmission(Field $field, array $value, ?Model $sender = null, ?string $uuid = null): Submission
    {
        return $this->createSubmissionAction->execute(
            SubmissionData::forField($field, $uuid ?: Str::orderedUuid()->toString(), $value, $sender),
        );
    }
}
