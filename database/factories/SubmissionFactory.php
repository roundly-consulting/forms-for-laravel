<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use RoundlyConsulting\Forms\Enums\SubmissionStatus;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Support\SubmissionModel;

/** @extends Factory<Submission> */
class SubmissionFactory extends Factory
{
    protected $model = Submission::class;

    /**
     * Build the model the host configured in `forms.models.submission`, not the packaged one,
     * so a host subclass comes out of the factory with its own events and observers.
     *
     * @return class-string<Submission>
     */
    public function modelName(): string
    {
        return SubmissionModel::class();
    }

    /** @return array<model-property<Submission>, mixed> */
    public function definition(): array
    {
        return [
            'uuid' => Str::orderedUuid()->toString(),
            'value' => ['value' => fake()->word()],
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SubmissionStatus::Draft,
        ]);
    }

    public function final(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SubmissionStatus::Final,
        ]);
    }
}
