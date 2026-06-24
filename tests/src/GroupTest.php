<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RoundlyConsulting\Forms\Models\Group;

it('has relationship to form', function () {
    $group = Group::factory()->make();

    expect($group->form())->toBeInstanceOf(BelongsTo::class);
});

it('has relationship to fields', function () {
    $group = Group::factory()->make();

    expect($group->fields())->toBeInstanceOf(HasMany::class);
});
