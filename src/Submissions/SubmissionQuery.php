<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Submissions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RoundlyConsulting\Forms\DataTransferObjects\AssembledSubmission;
use RoundlyConsulting\Forms\Enums\SubmissionStatus;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Support\FieldModel;
use RoundlyConsulting\Forms\Support\FormSubmissionModel;
use RoundlyConsulting\Forms\Support\SubmissionModel;

/**
 * `Forms::submissions($form)` — a fluent reader that assembles raw per-field submission
 * rows back into one keyed [field_key => value] set per uuid group, in form order. A field
 * key two groups of the form share is keyed `group_key.field_key` instead. Always scoped to
 * its form: a uuid of another form's submission matches nothing.
 */
final class SubmissionQuery
{
    private ?Model $sender = null;

    private bool $latest = true;

    private bool $includeDrafts = false;

    private ?string $uuid = null;

    private ?SubmissionStatus $status = null;

    /**
     * @internal build it with `Forms::submissions($form)`
     */
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

    /**
     * Only the submission with this uuid (a malformed uuid matches nothing).
     */
    public function whereUuid(string $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }

    /**
     * Only submissions under review — awaiting sign-off from the approvals engine.
     */
    public function pendingApproval(): self
    {
        return $this->whereStatus(SubmissionStatus::Pending);
    }

    /**
     * Only submissions whose review approved them.
     */
    public function approved(): self
    {
        return $this->whereStatus(SubmissionStatus::Approved);
    }

    /**
     * Only submissions whose review rejected them.
     */
    public function rejected(): self
    {
        return $this->whereStatus(SubmissionStatus::Rejected);
    }

    /** @return Collection<int, AssembledSubmission> */
    public function get(): Collection
    {
        // `submissions.uuid` is a uuid column: a strict engine rejects comparing a malformed
        // string against it, and such a string identifies nothing anyway.
        if ($this->uuid !== null && ! Str::isUuid($this->uuid)) {
            /** @var Collection<int, AssembledSubmission> $none */
            $none = new Collection;

            return $none;
        }

        $submissionModel = SubmissionModel::class();
        $aggregateModel = FormSubmissionModel::class();

        /** @var EloquentCollection<int, Submission> $rows */
        $rows = $submissionModel::query()
            ->where('form_id', $this->form->getKey())
            ->when(! $this->includeDrafts, fn (Builder $query) => $query->final())
            ->when($this->sender !== null, fn (Builder $query) => $query->whereMorphedTo('sender', $this->sender))
            ->when($this->uuid !== null, fn (Builder $query) => $query->where('uuid', $this->uuid))
            ->when($this->status !== null, fn (Builder $query) => $query->whereIn(
                'uuid',
                $aggregateModel::query()->select('uuid')->where('status', $this->status?->value),
            ))
            ->with(['field', 'group'])
            ->get();

        $shared = $this->sharedKeys();

        $grouped = $rows
            ->groupBy(fn (Submission $submission): string => $submission->uuid)
            ->map(fn (EloquentCollection $group): AssembledSubmission => $this->assemble($group, $shared))
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

    private function whereStatus(SubmissionStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * The field keys more than one group of the form uses — soft-deleted fields included,
     * since their answers are still read. Those are keyed `group_key.field_key` so neither
     * group's answer overwrites the other's; every other field keeps its bare key.
     *
     * @return array<string, true>
     */
    private function sharedKeys(): array
    {
        $fieldModel = FieldModel::class();

        /** @var array<string, true> $shared */
        $shared = $fieldModel::withTrashed()
            ->where('form_id', $this->form->getKey())
            ->get(['key', 'group_id'])
            ->groupBy(fn (Field $field): string => $field->key)
            ->filter(fn (EloquentCollection $fields): bool => $fields->pluck('group_id')->unique()->count() > 1)
            ->map(fn (): bool => true)
            ->all();

        return $shared;
    }

    /**
     * @param  EloquentCollection<int, Submission>  $group
     * @param  array<string, true>  $shared
     */
    private function assemble(EloquentCollection $group, array $shared): AssembledSubmission
    {
        /** @var Submission $first */
        $first = $group->first();

        $values = $group
            ->sortBy([
                fn (Submission $a, Submission $b): int => $a->group->order <=> $b->group->order,
                fn (Submission $a, Submission $b): int => $a->group_id <=> $b->group_id,
                fn (Submission $a, Submission $b): int => $a->field->order <=> $b->field->order,
                fn (Submission $a, Submission $b): int => $a->getKey() <=> $b->getKey(),
            ])
            ->mapWithKeys(fn (Submission $submission): array => [
                isset($shared[$submission->field->key])
                    ? $submission->group->key.'.'.$submission->field->key
                    : $submission->field->key => $submission->typedValue(),
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
