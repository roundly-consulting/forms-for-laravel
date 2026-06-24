<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use RoundlyConsulting\Forms\Models\Group;

/** @extends Factory<Group> */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    /** @return array<model-property<Group>, mixed> */
    public function definition(): array
    {
        $name = fake()->sentence(2);

        return [
            'name' => $name,
            'key' => Str::slug($name),
        ];
    }
}
