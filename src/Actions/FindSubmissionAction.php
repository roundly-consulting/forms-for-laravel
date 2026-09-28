<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Actions;

use Illuminate\Support\Str;
use RoundlyConsulting\Forms\Exceptions\SubmissionNotFoundException;
use RoundlyConsulting\Forms\Models\FormSubmission;
use RoundlyConsulting\Forms\Support\FormSubmissionModel;

/**
 * Looks up one whole submission (the aggregate grouping its field rows) by the uuid that
 * `submit()` / `draft()` return — a draft included.
 */
final readonly class FindSubmissionAction
{
    /**
     * @throws SubmissionNotFoundException when no submission carries the uuid, or it is
     *                                     malformed (a strict engine refuses to compare a
     *                                     non-uuid against a uuid column, so it can
     *                                     identify nothing)
     */
    public function execute(string $uuid): FormSubmission
    {
        if (! Str::isUuid($uuid)) {
            throw SubmissionNotFoundException::forUuid($uuid);
        }

        $model = FormSubmissionModel::class();

        /** @var FormSubmission|null $submission */
        $submission = $model::query()->where('uuid', $uuid)->first();

        return $submission ?? throw SubmissionNotFoundException::forUuid($uuid);
    }
}
