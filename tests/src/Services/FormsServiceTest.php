<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use RoundlyConsulting\Forms\Events\FormSubmitted;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Services\FormsService;

beforeEach(function () {
    $form = Form::factory()->public()->create(['key' => 'myform']);
    $group = Group::factory()->for($form)->create(['key' => 'mygroup']);
    Field::factory()->for($form)->for($group)->create([
        'key' => 'myfield',
        'validations' => ['required'],
    ]);
});

it('finds form with groups and fields by key', function () {
    /** @var FormsService $fs */
    $fs = resolve(FormsService::class);

    $instance = $fs->find('myform');

    expect($instance)->toBeInstanceOf(Form::class);
    expect($instance->relationLoaded('groups'))->toBeTrue();
    expect($instance->groups->first()?->relationLoaded('fields'))->toBeTrue();
});

it('it validates fields', function () {
    /** @var FormsService $fs */
    $fs = resolve(FormsService::class);

    $form = $fs->find('myform');

    $fs->validate($form, Request::create(
        uri: 'testing',
        parameters: [
            'myform' => [
                'mygroup' => [
                    'myfield' => '',
                ],
            ],
        ]
    ));
})->throws(ValidationException::class);

it('creates single submission of field', function () {
    /** @var FormsService $fs */
    $fs = resolve(FormsService::class);

    $form = $fs->find('myform');

    $group = $form->groups->first();
    $field = $group?->fields->first();

    $submission = $fs->createSubmission(
        field: $field,
        value: ['value' => 'ahoy'], // value resolved from field resolver
        sender: $group, // any model accepted, for testing purpose just use group
    );

    expect($submission)->toBeInstanceOf(Submission::class);
    expect($submission->value)->toBe(['value' => 'ahoy']);
    expect($submission->sender_type)->toBe(Group::class);
    expect($submission->sender_id)->toBe(1);

    expect(Submission::query()->where('uuid', $submission->uuid)->exists())->toBeTrue();
});

it('creates full form submission', function () {
    /** @var FormsService $fs */
    $fs = resolve(FormsService::class);

    Event::fake();

    $form = $fs->find('myform');

    $submission = $fs->submit(
        form: $form,
        request: Request::create(
            uri: 'testing',
            parameters: [
                'myform' => [
                    'mygroup' => [
                        'myfield' => 'Hi Mate',
                    ],
                ],
            ],
        ),
        sender: $form->groups->first(),
    )->uuid;

    expect($submission)->toBeString();
    expect(Submission::query()->where('uuid', $submission)->count())->toBe(1);

    Event::assertDispatched(function (FormSubmitted $e) use ($submission) {
        return $e->form->key === 'myform' &&
               $e->uuid === $submission &&
               $e->fieldCount === 1;
    });
});
