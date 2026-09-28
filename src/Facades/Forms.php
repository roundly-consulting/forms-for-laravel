<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Forms\DataTransferObjects\FormDefinitionData;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionResult;
use RoundlyConsulting\Forms\FormBuilder;
use RoundlyConsulting\Forms\FormsManager;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\FormSubmission;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\PendingSubmissionReview;
use RoundlyConsulting\Forms\SubmissionHandle;
use RoundlyConsulting\Forms\Submissions\SubmissionQuery;
use RoundlyConsulting\Forms\Testing\FormsFake;
use RoundlyConsulting\Forms\UpdateFormBuilder;

/**
 * @method static Form find(string $key)
 * @method static FormBuilder define(string $key, string $name)
 * @method static Form create(FormDefinitionData $data)
 * @method static UpdateFormBuilder update(string $key)
 * @method static list<string> sync(?array<int, array<string, mixed>> $definitions = null)
 * @method static array<string, mixed> validate(Form $form, Request $request)
 * @method static SubmissionResult submit(Form $form, Request $request, ?Model $sender = null, bool $bypassClosed = false)
 * @method static SubmissionResult draft(Form $form, Request $request, ?Model $sender = null, ?string $uuid = null, bool $bypassClosed = false)
 * @method static SubmissionResult finalize(string $uuid, bool $bypassClosed = false)
 * @method static SubmissionQuery submissions(Form $form)
 * @method static SubmissionHandle submission(string|FormSubmission $submission)
 * @method static PendingSubmissionReview review(string|FormSubmission $submission)
 * @method static Submission createSubmission(Field $field, array<array-key, mixed> $value, ?Model $sender = null, ?string $uuid = null, bool $bypassClosed = false)
 *
 * @see FormsManager
 */
final class Forms extends Facade
{
    /**
     * Swap the manager for a recording, still-performing fake (see FormsFake).
     */
    public static function fake(): FormsFake
    {
        $fake = new FormsFake(self::getFacadeApplication() ?? app());

        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return FormsManager::class;
    }
}
