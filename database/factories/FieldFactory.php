<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use RoundlyConsulting\Forms\Models\Field;

/** @extends Factory<Field> */
class FieldFactory extends Factory
{
    protected $model = Field::class;

    /** @return array<model-property<Field>, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->sentence(2);

        return [
            'name' => $name,
            'key' => Str::slug($name),
        ];
    }
}
