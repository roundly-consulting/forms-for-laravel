<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Resolvers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use RoundlyConsulting\Forms\Models\Submission;

/**
 * A resolver that needs the persisted {@see Submission} row to exist before it
 * can store its value — e.g. media-backed uploads, where the submission row is
 * the media owner. The store actions create the row first, then call
 * {@see AttachesToSubmission::attach()} inside the same transaction.
 */
interface AttachesToSubmission
{
    public function attach(Submission $submission, Request $request, ?Model $sender = null): void;

    /**
     * Rebuild the upload a stored row holds, so finalizing a draft validates the real file
     * against the field's rules (`file`, `mimes`, `max`, …) — the request that carried it is
     * long gone. Null when the row holds none.
     *
     * The file is a temporary copy; the caller deletes it once validation has run.
     */
    public function restoreUpload(Submission $submission): ?UploadedFile;
}
