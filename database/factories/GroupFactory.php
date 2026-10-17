<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Support\GroupModel;

/** @extends Factory<Group> */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    /**
     * Build the model the host configured in `forms.models.group`, not the packaged one,
     * so a host subclass comes out of the factory with its own events and observers.
     *
     * @return class-string<Group>
     */
    public function modelName(): string
    {
        return GroupModel::class();
    }

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
