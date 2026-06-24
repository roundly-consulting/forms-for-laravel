<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Forms\Models\Form;

it('casts attributes', function () {
    $form = Form::factory()->withExpiration()->public()->make();

    expect($form->is_public)->toBeTrue();
    expect($form->expires_at)->toBeInstanceOf(Carbon::class);
});

it('has relationship to submissions', function () {
    $form = Form::factory()->make();

    expect($form->submissions())->toBeInstanceOf(HasMany::class);
});

it('has relationship to groups', function () {
    $form = Form::factory()->make();

    expect($form->groups())->toBeInstanceOf(HasMany::class);
});

it('has relationship to fields', function () {
    $form = Form::factory()->make();

    expect($form->fields())->toBeInstanceOf(HasManyThrough::class);
});
