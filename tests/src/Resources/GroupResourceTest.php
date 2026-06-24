<?php

declare(strict_types=1);

use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Resources\GroupResource;

it('returns correct structure of resource', function () {
    $group = Group::factory()->make();
    $resource = GroupResource::make($group)->resolve();

    expect($resource['name'])->toBe($group->name)
        ->and($resource['key'])->toBe($group->key)
        ->and($resource['order'])->toBe($group->order);
});
