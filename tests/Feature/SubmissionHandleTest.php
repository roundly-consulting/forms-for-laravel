<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Forms\DataTransferObjects\AssembledSubmission;
use RoundlyConsulting\Forms\Enums\SubmissionStatus;
use RoundlyConsulting\Forms\Exceptions\DraftNotFoundException;
use RoundlyConsulting\Forms\Exceptions\SubmissionNotFoundException;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\GroupBuilder;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\FormSubmission;
use RoundlyConsulting\Forms\PendingSubmissionReview;
use RoundlyConsulting\Forms\SubmissionHandle;
use RoundlyConsulting\Forms\Tests\testable\Reviewer;

function handleForm(string $key = 'survey'): Form
{
    return Forms::define($key, ucfirst($key))
        ->public()
        ->group('g', 'G', function (GroupBuilder $g): void {
            $g->field('name', 'Name');
        })
        ->create();
}

function handleRequest(Form $form, string $name): Request
{
    return Request::create('t', parameters: [$form->key => ['g' => ['name' => $name]]]);
}

function reviewersTable(): void
{
    config()->set('forms.approvals.enabled', true);

    if (! Schema::hasTable('reviewers')) {
        Schema::create('reviewers', function ($table): void {
            $table->id();
            $table->timestamps();
        });
    }
}

it('reads one submission by the uuid submit returned', function (): void {
    $form = handleForm();
    $result = Forms::submit($form, handleRequest($form, 'Jane'));

    $handle = Forms::submission($result->uuid);

    expect($handle)->toBeInstanceOf(SubmissionHandle::class)
        ->and($handle->uuid())->toBe($result->uuid)
        ->and($handle->get())->toBeInstanceOf(AssembledSubmission::class)
        ->and($handle->get()->value('name'))->toBe('Jane')
        ->and($handle->model())->toBeInstanceOf(FormSubmission::class)
        ->and($handle->model()->status)->toBe(SubmissionStatus::Final);
});

it('reads and finalizes a draft through its handle', function (): void {
    $form = handleForm();
    $draft = Forms::draft($form, handleRequest($form, 'Draft Jane'));

    expect(Forms::submission($draft->uuid)->get()->value('name'))->toBe('Draft Jane')
        ->and(Forms::submission($draft->uuid)->model()->isDraft())->toBeTrue();

    $result = Forms::submission($draft->uuid)->finalize();

    expect($result->uuid)->toBe($draft->uuid)
        ->and(Forms::submission($draft->uuid)->model()->status)->toBe(SubmissionStatus::Final);
});

it('refuses to finalize a submission that is not a draft', function (): void {
    $form = handleForm();
    $result = Forms::submit($form, handleRequest($form, 'Jane'));

    Forms::submission($result->uuid)->finalize();
})->throws(DraftNotFoundException::class);

it('accepts the model and reuses it', function (): void {
    $form = handleForm();
    $result = Forms::submit($form, handleRequest($form, 'Jane'));
    $model = FormSubmission::query()->where('uuid', $result->uuid)->sole();

    $handle = Forms::submission($model);

    expect($handle->model())->toBe($model)
        ->and($handle->uuid())->toBe($result->uuid)
        ->and($handle->get()->value('name'))->toBe('Jane');
});

it('throws for an unknown or malformed uuid', function (string $uuid): void {
    expect(fn () => Forms::submission($uuid)->model())
        ->toThrow(SubmissionNotFoundException::class, "No submission found for UUID [{$uuid}].")
        ->and(fn () => Forms::submission($uuid)->get())->toThrow(SubmissionNotFoundException::class)
        ->and(fn () => Forms::review($uuid))->toThrow(SubmissionNotFoundException::class);
})->with([
    'unknown' => ['9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'],
    'malformed' => ['not-a-uuid'],
]);

it('throws when the aggregate exists but its field rows are gone', function (): void {
    $submission = FormSubmission::factory()->create();

    Forms::submission($submission->uuid)->get();
})->throws(SubmissionNotFoundException::class);

it('opens a review through the handle', function (): void {
    reviewersTable();
    $form = handleForm();
    $result = Forms::submit($form, handleRequest($form, 'Jane'));
    $reviewer = Reviewer::query()->create();

    $review = Forms::submission($result->uuid)->review();
    $request = $review->requiring([$reviewer])->any()->open();

    expect($review)->toBeInstanceOf(PendingSubmissionReview::class)
        ->and($request->status)->toBe(ApprovalStatus::Pending)
        ->and($request->rule)->toBe(ApprovalRule::Any)
        ->and(Forms::submission($result->uuid)->model()->status)->toBe(SubmissionStatus::Pending);
});

it('opens a review from a uuid on the flat verb', function (): void {
    reviewersTable();
    $form = handleForm();
    $result = Forms::submit($form, handleRequest($form, 'Jane'));

    $request = Forms::review($result->uuid)->requiring([Reviewer::query()->create()])->weighted(1)->open();

    expect($request->rule)->toBe(ApprovalRule::Weighted)
        ->and(Forms::submission($result->uuid)->model()->isPendingApproval())->toBeTrue();
});

it('filters submissions by review outcome and uuid', function (): void {
    reviewersTable();
    $form = handleForm();
    $pending = Forms::submit($form, handleRequest($form, 'Pending'));
    $approved = Forms::submit($form, handleRequest($form, 'Approved'));
    $rejected = Forms::submit($form, handleRequest($form, 'Rejected'));
    $plain = Forms::submit($form, handleRequest($form, 'Plain'));
    $reviewer = Reviewer::query()->create();

    foreach ([$pending, $approved, $rejected] as $result) {
        Forms::review($result->uuid)->requiring([$reviewer])->open();
    }

    $reviewer->approve(Forms::submission($approved->uuid)->model());
    $reviewer->reject(Forms::submission($rejected->uuid)->model(), 'no');

    $names = fn ($query): array => $query->get()->map(fn (AssembledSubmission $s) => $s->value('name'))->all();

    expect($names(Forms::submissions($form)->pendingApproval()))->toBe(['Pending'])
        ->and($names(Forms::submissions($form)->approved()))->toBe(['Approved'])
        ->and($names(Forms::submissions($form)->rejected()))->toBe(['Rejected'])
        ->and($names(Forms::submissions($form)->whereUuid($plain->uuid)))->toBe(['Plain'])
        ->and(Forms::submissions($form)->whereUuid('not-a-uuid')->count())->toBe(0)
        ->and(Forms::submissions($form)->count())->toBe(4);
});

it('never reads another form\'s submission through a form-scoped query', function (): void {
    $survey = handleForm('survey');
    $other = handleForm('other');
    $foreign = Forms::submit($other, handleRequest($other, 'Foreign'));

    expect(Forms::submissions($survey)->whereUuid($foreign->uuid)->first())->toBeNull()
        ->and(Forms::submissions($other)->whereUuid($foreign->uuid)->first()?->value('name'))->toBe('Foreign');
});
