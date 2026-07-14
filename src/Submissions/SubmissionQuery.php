<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Submissions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Forms\DataTransferObjects\AssembledSubmission;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Support\SubmissionModel;

/**
 * Fluent reader that assembles raw per-field submission rows back into one
 * keyed [field_key => value] set per uuid group.
 */
final class SubmissionQuery
{
    private ?Model $sender = null;

    private bool $latest = true;

    private bool $includeDrafts = false;

    public function __construct(private readonly Form $form) {}

    public function forSender(Model $sender): self
    {
        $this->sender = $sender;

        return $this;
    }

    public function latest(): self
    {
        $this->latest = true;

        return $this;
    }

    public function oldest(): self
    {
        $this->latest = false;

        return $this;
    }

    public function withDrafts(bool $withDrafts = true): self
    {
        $this->includeDrafts = $withDrafts;

        return $this;
    }

    /** @return Collection<int, AssembledSubmission> */
    public function get(): Collection
    {
        $submissionModel = SubmissionModel::class();

        /** @var EloquentCollection<int, Submission> $rows */
        $rows = $submissionModel::query()
            ->where('form_id', $this->form->getKey())
            ->when(! $this->includeDrafts, fn (Builder $query) => $query->final())
            ->when($this->sender !== null, fn (Builder $query) => $query->whereMorphedTo('sender', $this->sender))
            ->with(['field', 'group'])
            ->get();

        $grouped = $rows
            ->groupBy(fn (Submission $submission): string => $submission->uuid)
            ->map(fn (EloquentCollection $group): AssembledSubmission => $this->assemble($group))
            ->values();

        $sorted = $this->latest
            ? $grouped->sortByDesc(fn (AssembledSubmission $a): float => $a->submittedAt->getTimestamp())
            : $grouped->sortBy(fn (AssembledSubmission $a): float => $a->submittedAt->getTimestamp());

        /** @var Collection<int, AssembledSubmission> $result */
        $result = $sorted->values();

        return $result;
    }

    public function first(): ?AssembledSubmission
    {
        return $this->get()->first();
    }

    public function count(): int
    {
        return $this->get()->count();
    }

    /** @param  EloquentCollection<int, Submission>  $group */
    private function assemble(EloquentCollection $group): AssembledSubmission
    {
        /** @var Submission $first */
        $first = $group->first();

        $values = $group
            ->mapWithKeys(fn (Submission $submission): array => [
                $submission->field->key => $submission->typedValue(),
            ])
            ->all();

        return new AssembledSubmission(
            uuid: $first->uuid,
            values: $values,
            senderId: $first->sender_id,
            senderType: $first->sender_type,
            submittedAt: $first->created_at,
        );
    }
}
