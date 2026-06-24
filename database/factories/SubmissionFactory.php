<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use RoundlyConsulting\Forms\Enums\SubmissionStatus;
use RoundlyConsulting\Forms\Models\Submission;

/** @extends Factory<Submission> */
class SubmissionFactory extends Factory
{
    protected $model = Submission::class;

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
