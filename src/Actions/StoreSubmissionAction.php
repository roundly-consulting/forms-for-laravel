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
use RoundlyConsulting\Forms\Exceptions\FormSubmissionClosedException;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;

final class StoreSubmissionAction
{
    public function __construct(
        private readonly CreateSubmissionAction $createSubmission,
    ) {}

    public function execute(Form $form, Request $request, ?Model $sender = null, bool $bypassClosed = false): SubmissionResult
    {
        if (! $bypassClosed && ! $form->isAcceptingSubmissions()) {
            throw FormSubmissionClosedException::forKey($form->key);
        }

        $uuid = Str::orderedUuid()->toString();

        $fieldCount = $form->getConnection()->transaction(function () use ($form, $request, $sender, $uuid): int {
            return $form
                ->fields
                ->each(function (Field $field) use ($uuid, $request, $sender): void {
                    $value = $field->resolver()->toStorable(
                        request: $request,
                        sender: $sender,
                    );

                    $this->createSubmission->execute(
                        SubmissionData::forField($field, $uuid, $value, $sender, SubmissionStatus::Final),
                    );
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
