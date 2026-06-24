<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Forms\Database\Factories\SubmissionFactory;
use RoundlyConsulting\Forms\Enums\SubmissionStatus;

/**
 * @property int $id
 * @property string $uuid
 * @property int|null $sender_id
 * @property string|null $sender_type
 * @property int $form_id
 * @property int $group_id
 * @property int $field_id
 * @property array<array-key, mixed> $value
 * @property SubmissionStatus|null $status
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property CarbonInterface|null $deleted_at
 * @property-read Form $form
 * @property-read Group $group
 * @property-read Field $field
 */
class Submission extends Model
{
    /** @use HasFactory<SubmissionFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'value' => 'array',
            'status' => SubmissionStatus::class,
        ];
    }

    /**
     * Final submissions are those explicitly marked final or left without a
     * status, so submissions created before drafts existed remain final.
     *
     * @param  Builder<Submission>  $query
     * @return Builder<Submission>
     */
    public function scopeFinal(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNull('status')->orWhere('status', SubmissionStatus::Final->value);
        });
    }

    /**
     * @param  Builder<Submission>  $query
     * @return Builder<Submission>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', SubmissionStatus::Draft->value);
    }

    public function isDraft(): bool
    {
        return $this->status === SubmissionStatus::Draft;
    }

    public function path(): string
    {
        return implode('.', [
            $this->form->key,
            $this->group->key,
            $this->field->key,
        ]);
    }

    /** @return MorphTo<Model, $this> */
    public function sender(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Field, $this> */
    public function field(): BelongsTo
    {
        /** @var class-string<Field> $field */
        $field = config('forms.models.field', Field::class);

        return $this->belongsTo($field);
    }

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        /** @var class-string<Group> $group */
        $group = config('forms.models.group', Group::class);

        return $this->belongsTo($group);
    }

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        /** @var class-string<Form> $form */
        $form = config('forms.models.form', Form::class);

        return $this->belongsTo($form);
    }

    protected static function newFactory(): SubmissionFactory
    {
        return SubmissionFactory::new();
    }
}
