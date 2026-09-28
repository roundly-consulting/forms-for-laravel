<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionData;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionResult;
use RoundlyConsulting\Forms\Enums\SubmissionStatus;
use RoundlyConsulting\Forms\Events\FormSubmitted;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Resolvers\AttachesToSubmission;

final readonly class StoreSubmissionAction
{
    public function __construct(
        private CreateSubmissionAction $createSubmission,
        private CreateFormSubmissionAction $createFormSubmission,
    ) {}

    public function execute(Form $form, Request $request, ?Model $sender = null, bool $bypassClosed = false): SubmissionResult
    {
        if (! $bypassClosed) {
            $form->ensureAcceptingSubmissions();
        }

        $uuid = Str::orderedUuid()->toString();

        $input = $request->all();

        $fieldCount = $form->getConnection()->transaction(function () use ($form, $request, $sender, $uuid, $input): int {
            $aggregate = $this->createFormSubmission->execute($form, $uuid, $sender, SubmissionStatus::Final);

            return $form
                ->fields
                ->each(function (Field $field) use ($aggregate, $uuid, $request, $sender, $input): void {
                    // A field its conditions hide is skipped by validation, so whatever the
                    // request carries for it is unchecked: it has no answer, and none is kept.
                    $visible = $field->isVisible($input);
                    $resolver = $field->resolver();

                    $submission = $this->createSubmission->execute(
                        SubmissionData::forField(
                            $field,
                            $uuid,
                            $visible ? $resolver->toStorable($request, $sender) : ['value' => null],
                            $sender,
                            SubmissionStatus::Final,
                            $aggregate->getKey(),
                        ),
                    );

                    if ($visible && $resolver instanceof AttachesToSubmission) {
                        $resolver->attach($submission, $request, $sender);
                    }
                })
                ->count();
        });

        FormSubmitted::dispatch($form, $uuid, $fieldCount);

        return new SubmissionResult(
            uuid: $uuid,
            fieldCount: $fieldCount,
            submittedAt: now(),
        );
    }
}
