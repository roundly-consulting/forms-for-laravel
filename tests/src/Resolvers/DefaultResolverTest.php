<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Resolvers\DefaultResolver;

it('returns value from form submissions of specific field using additional query', function () {
    $form = Form::factory()->create(['key' => 'profile']);
    $group = Group::factory()->for($form)->create(['key' => 'user-details']);

    $field = Field::factory()
        ->for($form)
        ->for($group)
        ->create(['key' => 'home-address']);

    Submission::factory()
        ->for($form)
        ->for($group)
        ->for($field)
        ->create([
            'value' => [
                'value' => 'Hello',
            ],
        ]);

    $resolver = new DefaultResolver($field);

    DB::enableQueryLog();

    expect($resolver->fromStorage())->toBe('Hello');

    DB::disableQueryLog();

    expect(count(DB::getQueryLog()))->toBe(1); // Submissions Query
});

it('returns value from form submissions of specific field without making another query when submissions are eager loaded', function () {
    $form = Form::factory()->create(['key' => 'profile']);
    $group = Group::factory()->for($form)->create(['key' => 'user-details']);

    $field = Field::factory()
        ->for($form)
        ->for($group)
        ->create(['key' => 'home-address']);

    Submission::factory()
        ->for($form)
        ->for($group)
        ->for($field)
        ->create([
            'value' => [
                'value' => 'Hello',
            ],
        ]);

    $field->load('submissions');

    $resolver = new DefaultResolver($field);

    DB::enableQueryLog();

    expect($resolver->fromStorage())->toBe('Hello');

    DB::disableQueryLog();

    expect(count(DB::getQueryLog()))->toBe(0);
});

it('returns value to store in database', function () {
    $form = Form::factory()->create(['key' => 'myform']);
    $group = Group::factory()->for($form)->create(['key' => 'mygroup']);

    $field = Field::factory()
        ->for($form)
        ->for($group)
        ->create(['key' => 'myfield']);

    Submission::factory()
        ->for($form)
        ->for($group)
        ->for($field)
        ->create([
            'value' => [
                'value' => 'Hello',
            ],
        ]);

    $resolver = new DefaultResolver($field);

    $storable = $resolver->toStorable(
        request: Request::create(
            uri: 'testing',
            parameters: [
                'myform' => [
                    'mygroup' => [
                        'myfield' => 'Hi Jane!',
                    ],
                ],
            ],
        ),
    );

    expect($storable)->toBe(['value' => 'Hi Jane!']);
});
