<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use RoundlyConsulting\Forms\Enums\SubmissionStatus;
use RoundlyConsulting\Forms\Events\FormSubmitted;
use RoundlyConsulting\Forms\Exceptions\DraftNotFoundException;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\GroupBuilder;
use RoundlyConsulting\Forms\Models\Submission;

function draftForm(): void
{
    Forms::define('apply', 'Application')
        ->public()
        ->group('g', 'G', function (GroupBuilder $g): void {
            $g->field('name', 'Name')->rules(['required']);
            $g->field('email', 'Email')->rules(['required', 'email']);
        })
        ->create();
}

it('saves a partial draft without running validation', function () {
    draftForm();
    $form = Forms::find('apply');

    $result = Forms::draft($form, Request::create('t', parameters: [
        'apply' => ['g' => ['name' => 'Jane']],
    ]));

    expect(Submission::query()->where('uuid', $result->uuid)->draft()->count())->toBe(2)
        ->and(Submission::query()->final()->count())->toBe(0)
        ->and(Submission::query()->draft()->first()?->isDraft())->toBeTrue();
});

it('excludes drafts from final reads but keeps them retrievable', function () {
    draftForm();
    $form = Forms::find('apply');

    Forms::draft($form, Request::create('t', parameters: ['apply' => ['g' => ['name' => 'Jane']]]));

    expect(Submission::query()->final()->count())->toBe(0)
        ->and(Submission::query()->draft()->count())->toBe(2);
});

it('finalizes a draft after full validation passes', function () {
    Event::fake();
    draftForm();
    $form = Forms::find('apply');

    $draft = Forms::draft($form, Request::create('t', parameters: [
        'apply' => ['g' => ['name' => 'Jane', 'email' => 'jane@example.com']],
    ]));

    $result = Forms::finalize($draft->uuid);

    expect($result->uuid)->toBe($draft->uuid)
        ->and(Submission::query()->final()->where('uuid', $draft->uuid)->count())->toBe(2)
        ->and(Submission::query()->draft()->count())->toBe(0);

    Event::assertDispatched(fn (FormSubmitted $e): bool => $e->uuid === $draft->uuid);
});

it('rejects finalizing a draft that fails validation', function () {
    draftForm();
    $form = Forms::find('apply');

    $draft = Forms::draft($form, Request::create('t', parameters: [
        'apply' => ['g' => ['name' => 'Jane']],
    ]));

    expect(fn () => Forms::finalize($draft->uuid))->toThrow(ValidationException::class);

    expect(Submission::query()->draft()->count())->toBe(2);
});

it('throws when finalizing an unknown draft', function () {
    expect(fn () => Forms::finalize('missing-uuid'))
        ->toThrow(DraftNotFoundException::class);
});

it('resumes a draft by reusing its uuid', function () {
    draftForm();
    $form = Forms::find('apply');

    $first = Forms::draft($form, Request::create('t', parameters: ['apply' => ['g' => ['name' => 'Jane']]]));
    Forms::draft($form, Request::create('t', parameters: [
        'apply' => ['g' => ['name' => 'Jane', 'email' => 'jane@example.com']],
    ]), uuid: $first->uuid);

    $rows = Submission::query()->where('uuid', $first->uuid)->where('status', SubmissionStatus::Draft->value)->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->firstWhere('field_id', Forms::find('apply')->fields->firstWhere('key', 'email')?->id)?->value)
        ->toBe(['value' => 'jane@example.com']);
});
