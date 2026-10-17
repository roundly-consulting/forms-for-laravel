<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Support\FormModel;

/** @extends Factory<Form> */
class FormFactory extends Factory
{
    protected $model = Form::class;

    /**
     * Build the model the host configured in `forms.models.form`, not the packaged one,
     * so a host subclass comes out of the factory with its own events and observers.
     *
     * @return class-string<Form>
     */
    public function modelName(): string
    {
        return FormModel::class();
    }

    /** @return array<model-property<Form>, mixed> */
    public function definition(): array
    {
        $name = fake()->sentence(3);

        return [
            'name' => $name,
            'key' => Str::slug($name),
        ];
    }

    public function withExpiration(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => Carbon::parse(fake()->dateTime()),
        ]);
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_public' => true,
        ]);
    }
}
