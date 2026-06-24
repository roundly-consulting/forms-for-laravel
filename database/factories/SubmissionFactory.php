<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
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
}
