<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;

it('filters public forms', function () {
    Form::factory()->public()->create(['key' => 'a']);
    Form::factory()->create(['key' => 'b']);

    expect(Form::query()->public()->pluck('key')->all())->toBe(['a']);
});

it('filters active and expired forms', function () {
    Carbon::setTestNow('2030-01-01 12:00:00');

    Form::factory()->create(['key' => 'no-expiry']);
    Form::factory()->create(['key' => 'future', 'expires_at' => Carbon::parse('2030-02-01')]);
    Form::factory()->create(['key' => 'past', 'expires_at' => Carbon::parse('2029-12-01')]);

    expect(Form::query()->active()->pluck('key')->sort()->values()->all())
        ->toBe(['future', 'no-expiry']);

    expect(Form::query()->expired()->pluck('key')->all())->toBe(['past']);

    Carbon::setTestNow();
});

it('finds forms by key', function () {
    Form::factory()->create(['key' => 'a']);
    Form::factory()->create(['key' => 'b']);

    expect(Form::query()->forKey('b')->sole()->key)->toBe('b');
});

it('orders groups and fields by order', function () {
    $form = Form::factory()->create();
    Group::factory()->for($form)->create(['key' => 'second', 'order' => 1]);
    Group::factory()->for($form)->create(['key' => 'first', 'order' => 0]);

    expect(Group::query()->ordered()->pluck('key')->all())->toBe(['first', 'second']);

    $group = Group::query()->first();
    Field::factory()->for($form)->for($group)->create(['key' => 'b', 'order' => 1]);
    Field::factory()->for($form)->for($group)->create(['key' => 'a', 'order' => 0]);

    expect(Field::query()->ordered()->pluck('key')->all())->toBe(['a', 'b']);
});
