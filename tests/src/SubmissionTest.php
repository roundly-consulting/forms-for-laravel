<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Models\Submission;

it('casts attributes', function () {
    $submission = Submission::factory()->make([
        'value' => ['one', 'two'],
    ]);

    expect($submission->value)->toBe(['one', 'two']);
});

it('has relationship to form', function () {
    $submission = Submission::factory()->make();

    expect($submission->form())->toBeInstanceOf(BelongsTo::class);
});

it('has relationship to group', function () {
    $submission = Submission::factory()->make();

    expect($submission->group())->toBeInstanceOf(BelongsTo::class);
});

it('has relationship to field', function () {
    $submission = Submission::factory()->make();

    expect($submission->field())->toBeInstanceOf(BelongsTo::class);
});

it('has relationship to sender', function () {
    $submission = Submission::factory()->make();

    expect($submission->sender())->toBeInstanceOf(MorphTo::class);
});

it('returns path of field generated from field, form and group', function () {
    $form = Form::factory()->create(['key' => 'profile']);
    $group = Group::factory()->for($form)->create(['key' => 'user-details']);

    $field = Field::factory()
        ->for($form)
        ->for($group)
        ->create(['key' => 'home-address']);

    $submission = Submission::factory()
        ->for($form)
        ->for($group)
        ->for($field)
        ->create();

    expect($submission->path())->toBe('profile.user-details.home-address');
});
