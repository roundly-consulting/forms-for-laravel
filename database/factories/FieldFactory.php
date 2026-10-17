<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Support\FieldModel;

/** @extends Factory<Field> */
class FieldFactory extends Factory
{
    protected $model = Field::class;

    /**
     * Build the model the host configured in `forms.models.field`, not the packaged one,
     * so a host subclass comes out of the factory with its own events and observers.
     *
     * @return class-string<Field>
     */
    public function modelName(): string
    {
        return FieldModel::class();
    }

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
