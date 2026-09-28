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
use RoundlyConsulting\Forms\Concerns\HasSubmissionMedia;
use RoundlyConsulting\Forms\Database\Factories\SubmissionFactory;
use RoundlyConsulting\Forms\Enums\SubmissionStatus;
use RoundlyConsulting\Forms\Support\FieldModel;
use RoundlyConsulting\Forms\Support\FormModel;
use RoundlyConsulting\Forms\Support\FormSubmissionModel;
use RoundlyConsulting\Forms\Support\GroupModel;
use RoundlyConsulting\MediaLibrary\Contracts\HasMedia;

/**
 * @property int $id
 * @property string $uuid
 * @property int|null $form_submission_id
 * @property int|string|null $sender_id
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
 * @property-read FormSubmission|null $formSubmission
 */
class Submission extends Model implements HasMedia
{
    /** @use HasFactory<SubmissionFactory> */
    use HasFactory;

    use HasSubmissionMedia;
    use SoftDeletes;

    protected $guarded = [];

    /**
     * Cast the stored field value to the real PHP type mapped from the field's
     * definition (number -> int, checkbox -> bool, date -> Carbon, ...), using
     * roundly-consulting/attributes-for-laravel as the type system. Unmapped
     * field types fall back to the raw string value.
     */
    public function typedValue(): mixed
    {
        $raw = $this->value['value'] ?? null;

        return $this->field->castStoredValue($raw);
    }

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

    /**
     * The field this row answers — a soft-deleted one included, so removing a field from a
     * form never breaks reading the answers already given to it.
     *
     * @return BelongsTo<Field, $this>
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo(FieldModel::class(), 'field_id')->withTrashed();
    }

    /**
     * The field's group — a soft-deleted one included.
     *
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(GroupModel::class(), 'group_id')->withTrashed();
    }

    /**
     * The form — a soft-deleted one included.
     *
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(FormModel::class(), 'form_id')->withTrashed();
    }

    /** @return BelongsTo<FormSubmission, $this> */
    public function formSubmission(): BelongsTo
    {
        return $this->belongsTo(FormSubmissionModel::class(), 'form_submission_id');
    }

    protected static function newFactory(): SubmissionFactory
    {
        return SubmissionFactory::new();
    }
}
