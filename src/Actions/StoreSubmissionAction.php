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

        $fieldCount = $form->getConnection()->transaction(function () use ($form, $request, $sender, $uuid): int {
            $aggregate = $this->createFormSubmission->execute($form, $uuid, $sender, SubmissionStatus::Final);

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
                            SubmissionStatus::Final,
                            $aggregate->getKey(),
                        ),
                    );

                    if ($resolver instanceof AttachesToSubmission) {
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
