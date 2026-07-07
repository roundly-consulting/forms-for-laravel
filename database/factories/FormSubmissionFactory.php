<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use RoundlyConsulting\Forms\Enums\SubmissionStatus;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\FormSubmission;

/** @extends Factory<FormSubmission> */
class FormSubmissionFactory extends Factory
{
    protected $model = FormSubmission::class;

    /** @return array<model-property<FormSubmission>, mixed> */
    public function definition(): array
    {
        return [
            'uuid' => Str::orderedUuid()->toString(),
            'form_id' => Form::factory(),
            'status' => SubmissionStatus::Final,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SubmissionStatus::Draft,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SubmissionStatus::Pending,
        ]);
    }
}
