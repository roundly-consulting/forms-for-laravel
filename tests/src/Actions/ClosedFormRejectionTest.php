<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Forms\Actions\FindFormAction;
use RoundlyConsulting\Forms\Actions\StoreSubmissionAction;
use RoundlyConsulting\Forms\Events\FormSubmitted;
use RoundlyConsulting\Forms\Exceptions\FormSubmissionClosedException;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\FormSubmission;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Tests\testable\Submitter;

function seedSubmittable(bool $public, ?string $expiresAt = null): Form
{
    $form = Form::factory()->create([
        'key' => 'myform',
        'is_public' => $public,
        'expires_at' => $expiresAt,
    ]);
    $group = Group::factory()->for($form)->create(['key' => 'g']);
    Field::factory()->for($form)->for($group)->create(['key' => 'one']);

    return $form;
}

function closedRequest(): Request
{
    return Request::create('t', parameters: ['myform' => ['g' => ['one' => 'a']]]);
}

it('rejects submissions to a non-public form', function () {
    seedSubmittable(public: false);
    $form = app(FindFormAction::class)->execute('myform');

    expect(fn () => app(StoreSubmissionAction::class)->execute($form, closedRequest()))
        ->toThrow(FormSubmissionClosedException::class);

    expect(Submission::query()->count())->toBe(0);
});

it('rejects submissions to an expired form', function () {
    Carbon::setTestNow('2030-01-01 12:00:00');
    seedSubmittable(public: true, expiresAt: '2029-01-01 00:00:00');
    $form = app(FindFormAction::class)->execute('myform');

    expect(fn () => app(StoreSubmissionAction::class)->execute($form, closedRequest()))
        ->toThrow(FormSubmissionClosedException::class);

    Carbon::setTestNow();
});

it('allows a bypass for internal submissions', function () {
    seedSubmittable(public: false);
    $form = app(FindFormAction::class)->execute('myform');

    $result = app(StoreSubmissionAction::class)->execute($form, closedRequest(), bypassClosed: true);

    expect($result->fieldCount)->toBe(1)
        ->and(Submission::query()->count())->toBe(1);
});

it('accepts submissions to an open public form', function () {
    seedSubmittable(public: true);
    $form = app(FindFormAction::class)->execute('myform');

    $result = app(StoreSubmissionAction::class)->execute($form, closedRequest());

    expect($result->fieldCount)->toBe(1);
});

/*
 * Every path to a final submission enforces the same closed-form rule as submit(). Before,
 * draft() + finalize() (and createSubmission()) never asked whether the form was accepting
 * submissions, so a non-public or expired form still took final submissions that way.
 */

it('rejects drafting on a closed form', function (bool $public, ?string $expiresAt) {
    Carbon::setTestNow('2030-01-01 12:00:00');
    $form = seedSubmittable(public: $public, expiresAt: $expiresAt);

    expect(fn () => Forms::draft($form, closedRequest()))->toThrow(FormSubmissionClosedException::class)
        ->and(Submission::query()->count())->toBe(0)
        ->and(FormSubmission::query()->count())->toBe(0);

    Carbon::setTestNow();
})->with([
    'non-public' => [false, null],
    'expired' => [true, '2029-01-01 00:00:00'],
]);

it('rejects finalizing a draft once the form has closed', function (string $closeBy) {
    Event::fake([FormSubmitted::class]);
    Carbon::setTestNow('2030-01-01 12:00:00');
    $form = seedSubmittable(public: true, expiresAt: '2030-06-01 00:00:00');

    $draft = Forms::draft($form, closedRequest());

    $closeBy === 'unpublished'
        ? $form->update(['is_public' => false])
        : Carbon::setTestNow('2030-07-01 12:00:00');

    expect(fn () => Forms::finalize($draft->uuid))->toThrow(FormSubmissionClosedException::class)
        ->and(Submission::query()->draft()->count())->toBe(1)
        ->and(Submission::query()->final()->count())->toBe(0)
        ->and(FormSubmission::query()->sole()->isDraft())->toBeTrue();

    Event::assertNotDispatched(FormSubmitted::class);
    Carbon::setTestNow();
})->with(['unpublished', 'expired']);

it('allows a bypass for drafting and finalizing on a closed form', function () {
    $form = seedSubmittable(public: false);

    $draft = Forms::draft($form, closedRequest(), bypassClosed: true);
    $result = Forms::finalize($draft->uuid, bypassClosed: true);

    expect($result->fieldCount)->toBe(1)
        ->and(Submission::query()->final()->count())->toBe(1);
});

it('drafts and finalizes through the sender trait under the same rule', function () {
    $form = seedSubmittable(public: false);
    $sender = Submitter::query()->create();

    expect(fn () => $sender->draftTo($form, closedRequest()))->toThrow(FormSubmissionClosedException::class)
        ->and($sender->draftTo($form, closedRequest(), bypassClosed: true)->fieldCount)->toBe(1);
});

it('rejects a single-row submission on a closed form unless bypassed', function () {
    seedSubmittable(public: false);
    $field = Field::query()->where('key', 'one')->sole();

    expect(fn () => Forms::createSubmission($field, ['value' => 'a']))->toThrow(FormSubmissionClosedException::class)
        ->and(Submission::query()->count())->toBe(0)
        ->and(Forms::createSubmission($field, ['value' => 'a'], bypassClosed: true)->exists)->toBeTrue();
});
