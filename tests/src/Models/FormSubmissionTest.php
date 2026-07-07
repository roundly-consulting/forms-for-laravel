<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use RoundlyConsulting\Approvals\Interfaces\RequiresApprovalInterface;
use RoundlyConsulting\Forms\Actions\FindFormAction;
use RoundlyConsulting\Forms\Actions\StoreSubmissionAction;
use RoundlyConsulting\Forms\Enums\SubmissionStatus;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\GroupBuilder;
use RoundlyConsulting\Forms\Models\FormSubmission;
use RoundlyConsulting\Forms\Models\Submission;

function reviewableForm(): void
{
    Forms::define('survey', 'Survey')
        ->public()
        ->group('g', 'G', function (GroupBuilder $g): void {
            $g->field('name', 'Name');
            $g->field('color', 'Color');
        })
        ->create();
}

it('is an approvals subject', function () {
    expect(FormSubmission::factory()->make())->toBeInstanceOf(RequiresApprovalInterface::class);
});

it('creates an aggregate grouping the per-field rows on submit', function () {
    reviewableForm();
    $form = app(FindFormAction::class)->execute('survey');

    $result = app(StoreSubmissionAction::class)->execute($form, Request::create('t', parameters: [
        'survey' => ['g' => ['name' => 'Jane', 'color' => 'blue']],
    ]));

    $aggregate = FormSubmission::query()->where('uuid', $result->uuid)->sole();

    expect(FormSubmission::query()->count())->toBe(1)
        ->and($aggregate->status)->toBe(SubmissionStatus::Final)
        ->and($aggregate->submissions()->count())->toBe(2)
        ->and(Submission::query()->whereNotNull('form_submission_id')->count())->toBe(2)
        ->and($aggregate->submissions->first()?->formSubmission->is($aggregate))->toBeTrue();
});

it('creates a draft aggregate and finalizes it', function () {
    reviewableForm();
    $form = app(FindFormAction::class)->execute('survey');

    $result = Forms::draft($form, Request::create('t', parameters: ['survey' => ['g' => ['name' => 'Jane']]]));

    $aggregate = FormSubmission::query()->where('uuid', $result->uuid)->sole();
    expect($aggregate->status)->toBe(SubmissionStatus::Draft)
        ->and($aggregate->isDraft())->toBeTrue();

    Forms::finalize($result->uuid);

    expect($aggregate->fresh()?->status)->toBe(SubmissionStatus::Final);
});

it('reuses one aggregate when a draft is resumed', function () {
    reviewableForm();
    $form = app(FindFormAction::class)->execute('survey');

    $result = Forms::draft($form, Request::create('t', parameters: ['survey' => ['g' => ['name' => 'a']]]));
    Forms::draft($form, Request::create('t', parameters: ['survey' => ['g' => ['name' => 'b']]]), null, $result->uuid);

    expect(FormSubmission::query()->count())->toBe(1);
});

it('relates to its form and sender', function () {
    reviewableForm();
    $form = app(FindFormAction::class)->execute('survey');

    $result = app(StoreSubmissionAction::class)->execute($form, Request::create('t', parameters: [
        'survey' => ['g' => ['name' => 'Jane', 'color' => 'blue']],
    ]));

    $aggregate = FormSubmission::query()->where('uuid', $result->uuid)->sole();

    expect($aggregate->form->is($form))->toBeTrue();
});

it('reports status-driven predicates and the pending scope', function () {
    $pending = FormSubmission::factory()->pending()->create();
    $approved = FormSubmission::factory()->create(['status' => SubmissionStatus::Approved]);

    expect($pending->isPendingApproval())->toBeTrue()
        ->and($approved->isApproved())->toBeTrue()
        ->and($approved->isPendingApproval())->toBeFalse()
        ->and(FormSubmission::query()->pendingApproval()->pluck('id')->all())->toBe([$pending->id]);
});
