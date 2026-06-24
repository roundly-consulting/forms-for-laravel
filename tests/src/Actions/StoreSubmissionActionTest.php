<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Forms\Actions\FindFormAction;
use RoundlyConsulting\Forms\Actions\StoreSubmissionAction;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionResult;
use RoundlyConsulting\Forms\Events\FormSubmitted;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Tests\testable\ThrowingResolver;

beforeEach(function () {
    $form = Form::factory()->create(['key' => 'myform']);
    $group = Group::factory()->for($form)->create(['key' => 'mygroup']);
    Field::factory()->for($form)->for($group)->create(['key' => 'one', 'order' => 0]);
    Field::factory()->for($form)->for($group)->create(['key' => 'two', 'order' => 1]);
});

function submitRequest(): Request
{
    return Request::create('testing', parameters: [
        'myform' => ['mygroup' => ['one' => 'a', 'two' => 'b']],
    ]);
}

it('stores one submission per field and returns a result', function () {
    Carbon::setTestNow('2030-01-01 12:00:00');
    Event::fake();

    $form = app(FindFormAction::class)->execute('myform');

    $result = app(StoreSubmissionAction::class)->execute($form, submitRequest());

    expect($result)->toBeInstanceOf(SubmissionResult::class)
        ->and($result->fieldCount)->toBe(2)
        ->and($result->submittedAt->equalTo(Carbon::parse('2030-01-01 12:00:00')))->toBeTrue()
        ->and(Submission::query()->where('uuid', $result->uuid)->count())->toBe(2);

    Event::assertDispatched(fn (FormSubmitted $e): bool => $e->uuid === $result->uuid && $e->fieldCount === 2);

    Carbon::setTestNow();
});

it('rolls back every submission when a resolver throws mid-loop', function () {
    config()->set('forms.fields.custom', ThrowingResolver::class);

    $form = app(FindFormAction::class)->execute('myform');
    // Make the second field use the throwing resolver.
    $form->fields->last()?->update(['type' => 'custom']);
    $form = app(FindFormAction::class)->execute('myform');

    expect(fn () => app(StoreSubmissionAction::class)->execute($form, submitRequest()))
        ->toThrow(RuntimeException::class);

    expect(Submission::query()->count())->toBe(0);
});
