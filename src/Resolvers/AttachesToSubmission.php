<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Resolvers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
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
}
