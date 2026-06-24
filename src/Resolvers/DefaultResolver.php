<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Resolvers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Submission;

class DefaultResolver implements Resolver
{
    public function __construct(protected Field $field) {}

    /** @return array<array-key, mixed> */
    public function toStorable(Request $request, ?Model $sender = null): array
    {
        return ['value' => $request->input($this->field->path())];
    }

    public function fromStorage(?Model $sender = null): mixed
    {
        if ($this->field->relationLoaded('submissions')) {
            /** @var array<array-key, mixed>|null $value */
            $value = $this->field->submissions
                ->when(
                    ! is_null($sender),
                    fn (Collection $submissions): Collection => $submissions->filter(
                        fn (Submission $submission): bool => $submission->sender()->is($sender),
                    ),
                )
                ->firstWhere(fn (Submission $submission): bool => $submission->field_id === $this->field->getKey())
                ?->value;
        } else {
            /** @var array<array-key, mixed>|null $value */
            $value = $this->field->submissions()
                ->when(! is_null($sender), fn (Builder $query) => $query->whereMorphedTo('sender', $sender))
                ->where('field_id', $this->field->getKey())
                ->value('value');
        }

        return $value !== null ? ($value['value'] ?? null) : null;
    }
}
