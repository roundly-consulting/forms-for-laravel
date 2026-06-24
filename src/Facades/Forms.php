<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Facades;

use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Forms\DataTransferObjects\FormDefinitionData;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionResult;
use RoundlyConsulting\Forms\FormBuilder;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Services\FormsService;
use RoundlyConsulting\Forms\Submissions\SubmissionQuery;
use RoundlyConsulting\Forms\UpdateFormBuilder;

/**
 * @method static Form find(string $key)
 * @method static FormBuilder define(string $key, string $name)
 * @method static Form create(FormDefinitionData $data)
 * @method static UpdateFormBuilder update(string $key)
 * @method static list<string> sync(?array<int, array<string, mixed>> $definitions = null)
 * @method static array<string, mixed> validate(Form $form, \Illuminate\Http\Request $request)
 * @method static SubmissionResult submit(Form $form, \Illuminate\Http\Request $request, ?\Illuminate\Database\Eloquent\Model $sender = null, bool $bypassClosed = false)
 * @method static string storeSubmission(Form $form, \Illuminate\Http\Request $request, ?\Illuminate\Database\Eloquent\Model $sender = null, bool $bypassClosed = false)
 * @method static SubmissionResult draft(Form $form, \Illuminate\Http\Request $request, ?\Illuminate\Database\Eloquent\Model $sender = null, ?string $uuid = null)
 * @method static SubmissionResult finalize(string $uuid)
 * @method static SubmissionQuery submissions(Form $form)
 * @method static Submission createSubmission(Field $field, array<array-key, mixed> $value, ?\Illuminate\Database\Eloquent\Model $sender = null, ?string $uuid = null)
 * @method static \RoundlyConsulting\Forms\Testing\FormsFake fake()
 *
 * @see FormsService
 */
final class Forms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'forms.manager';
    }
}
