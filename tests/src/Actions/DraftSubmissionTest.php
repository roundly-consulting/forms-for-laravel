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
use RoundlyConsulting\Forms\Models\FormSubmission;
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

/**
 * Both shapes of "no such draft" must answer the same way on every engine.
 *
 * The malformed case is the one that shipped broken: `submissions.uuid` is a real uuid
 * column, so Postgres refuses to compare 'missing-uuid' against it and raises
 * `SQLSTATE[22P02] invalid input syntax for type uuid` from inside the query — a raw
 * QueryException, not DraftNotFoundException, and never the documented exception a host
 * catches. SQLite stores the column as text, compares happily, matches nothing, and the
 * intended exception fired — which is exactly why 204 green tests never saw it.
 *
 * The well-formed-but-absent case is the control: it proves the guard did not simply
 * swallow every lookup.
 */
it('throws when finalizing an unknown draft', function (string $uuid) {
    expect(fn () => Forms::finalize($uuid))
        ->toThrow(DraftNotFoundException::class);
})->with([
    'malformed id' => 'missing-uuid',
    'well-formed but absent id' => '019f6eef-76b8-73b9-ab3c-78c1a64965a7',
]);

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

it('refuses to resume a finalized submission as a draft', function () {
    // Regression: resuming only cleared *draft* rows under the uuid, then flipped the
    // aggregate back to Draft and wrote fresh draft rows next to the final ones — a finished
    // submission (already announced via FormSubmitted, possibly under review) reopened, and
    // finalizing it again left two final rows per field under one uuid.
    draftForm();
    $form = Forms::find('apply');
    $values = ['apply' => ['g' => ['name' => 'Jane', 'email' => 'jane@example.com']]];

    $final = Forms::submit($form, Request::create('t', parameters: $values));

    expect(fn () => Forms::draft($form, Request::create('t', parameters: $values), uuid: $final->uuid))
        ->toThrow(DraftNotFoundException::class)
        ->and(FormSubmission::query()->sole()->status)->toBe(SubmissionStatus::Final)
        ->and(Submission::query()->where('uuid', $final->uuid)->count())->toBe(2)
        ->and(Submission::query()->where('uuid', $final->uuid)->draft()->count())->toBe(0);
});

it('refuses to resume another form\'s draft', function () {
    draftForm();
    Forms::define('other', 'Other')
        ->public()
        ->group('g', 'G', function (GroupBuilder $g): void {
            $g->field('note', 'Note');
        })
        ->create();

    $draft = Forms::draft(Forms::find('apply'), Request::create('t', parameters: ['apply' => ['g' => ['name' => 'Jane']]]));

    expect(fn () => Forms::draft(Forms::find('other'), Request::create('t', parameters: ['other' => ['g' => ['note' => 'x']]]), uuid: $draft->uuid))
        ->toThrow(DraftNotFoundException::class)
        ->and(FormSubmission::query()->sole()->form_id)->toBe(Forms::find('apply')->getKey())
        ->and(Submission::query()->where('uuid', $draft->uuid)->draft()->count())->toBe(2);
});

it('refuses to resume a malformed draft uuid', function () {
    draftForm();

    Forms::draft(Forms::find('apply'), Request::create('t', parameters: ['apply' => ['g' => ['name' => 'Jane']]]), uuid: 'not-a-uuid');
})->throws(DraftNotFoundException::class);
