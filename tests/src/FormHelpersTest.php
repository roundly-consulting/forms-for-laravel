<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;

it('reports expiry via isExpired', function () {
    Carbon::setTestNow('2030-01-01 12:00:00');

    expect(Form::factory()->create(['expires_at' => null])->isExpired())->toBeFalse()
        ->and(Form::factory()->create(['expires_at' => Carbon::parse('2029-01-01')])->isExpired())->toBeTrue()
        ->and(Form::factory()->create(['expires_at' => Carbon::parse('2031-01-01')])->isExpired())->toBeFalse();

    Carbon::setTestNow();
});

it('reports acceptance via isAcceptingSubmissions', function () {
    Carbon::setTestNow('2030-01-01 12:00:00');

    expect(Form::factory()->public()->create(['expires_at' => null])->isAcceptingSubmissions())->toBeTrue()
        ->and(Form::factory()->create(['expires_at' => null])->isAcceptingSubmissions())->toBeFalse()
        ->and(Form::factory()->public()->create(['expires_at' => Carbon::parse('2029-01-01')])->isAcceptingSubmissions())->toBeFalse();

    Carbon::setTestNow();
});

it('reports required state via Field::isRequired', function () {
    expect(Field::factory()->make(['validations' => ['required', 'email']])->isRequired())->toBeTrue()
        ->and(Field::factory()->make(['validations' => ['required_if:other,1']])->isRequired())->toBeFalse() // required only under its condition
        ->and(Field::factory()->make(['validations' => ['email']])->isRequired())->toBeFalse()
        ->and(Field::factory()->make(['validations' => null])->isRequired())->toBeFalse();
});
