<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Services;

use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RoundlyConsulting\Forms\Events\FormSubmitted;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Models\Submission;

class FormsService
{
    public function find(string $key): Form
    {
        /** @var Form $form */
        $form = $this->newFormsQuery()
            ->where('key', $key)
            ->with(['groups' => function (BuilderContract $groups): void {
                /** @var Builder<Group> $groups */
                $groups
                    ->oldest('order')
                    ->with(['fields' => function (BuilderContract $fields): void {
                        /** @var Builder<Field> $fields */
                        $fields->oldest('order');
                    }]);
            }])
            ->sole();

        return $form;
    }

    public function validate(Form $form, Request $request): void
    {
        $fieldsToValidate = $form
            ->fields
            ->filter(fn (Field $field): bool => ! is_null($field->validations));

        $validator = validator(
            data: $request->all(),
            rules: $fieldsToValidate->mapWithKeys(fn (Field $field): array => [
                $field->path() => $field->validations,
            ])->toArray(),
            attributes: $fieldsToValidate->mapWithKeys(fn (Field $field): array => [
                $field->path() => $field->name,
            ])->toArray(),
        );

        $validator->validated();
    }

    public function storeSubmission(Form $form, Request $request, ?Model $sender = null): string
    {
        $submission = Str::orderedUuid()->toString();

        $form
            ->fields
            ->each(function (Field $field) use ($submission, $request, $sender): void {
                $value = $field->resolver()->toStorable(
                    request: $request,
                    sender: $sender,
                );

                $this->createSubmission(
                    field: $field,
                    value: $value,
                    sender: $sender,
                    uuid: $submission,
                );
            });

        FormSubmitted::dispatch($form, $submission);

        return $submission;
    }

    /** @param  array<array-key, mixed>  $value */
    public function createSubmission(Field $field, array $value, ?Model $sender = null, ?string $uuid = null): Submission
    {
        /** @var class-string<Submission> $model */
        $model = config('forms.models.submission', Submission::class);

        $submission = new $model([
            'uuid' => $uuid ?: Str::orderedUuid()->toString(),
            'sender_id' => $sender?->getKey(),
            'sender_type' => $sender?->getMorphClass(),
            'form_id' => $field->form_id,
            'group_id' => $field->group_id,
            'field_id' => $field->getKey(),
            'value' => $value,
        ]);

        return tap($submission)->save();
    }

    /** @return Builder<Form> */
    protected function newFormsQuery(): Builder
    {
        /** @var class-string<Form> $form */
        $form = config('forms.models.form', Form::class);

        return $form::query();
    }
}
