<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Forms\Actions\FindFormAction;
use RoundlyConsulting\Forms\Actions\StoreSubmissionAction;
use RoundlyConsulting\Forms\Exceptions\FormSubmissionClosedException;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Models\Submission;

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
