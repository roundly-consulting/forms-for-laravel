<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RoundlyConsulting\Forms\Actions\CreateFormAction;
use RoundlyConsulting\Forms\Actions\CreateSubmissionAction;
use RoundlyConsulting\Forms\Actions\FindFormAction;
use RoundlyConsulting\Forms\Actions\StoreSubmissionAction;
use RoundlyConsulting\Forms\Actions\ValidateSubmissionAction;
use RoundlyConsulting\Forms\DataTransferObjects\FormDefinitionData;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionData;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionResult;
use RoundlyConsulting\Forms\FormBuilder;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Submission;

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
    ) {}

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

    /** @return array<string, mixed> */
    public function validate(Form $form, Request $request): array
    {
        return $this->validateSubmission->execute($form, $request);
    }

    public function submit(Form $form, Request $request, ?Model $sender = null): SubmissionResult
    {
        return $this->storeSubmission->execute($form, $request, $sender);
    }

    public function storeSubmission(Form $form, Request $request, ?Model $sender = null): string
    {
        return $this->submit($form, $request, $sender)->uuid;
    }

    /** @param  array<array-key, mixed>  $value */
    public function createSubmission(Field $field, array $value, ?Model $sender = null, ?string $uuid = null): Submission
    {
        return $this->createSubmissionAction->execute(
            SubmissionData::forField($field, $uuid ?: Str::orderedUuid()->toString(), $value, $sender),
        );
    }
}
