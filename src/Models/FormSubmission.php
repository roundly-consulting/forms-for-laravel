<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Approvals\Interfaces\RequiresApprovalInterface;
use RoundlyConsulting\Approvals\Traits\RequiresApproval;
use RoundlyConsulting\Forms\Database\Factories\FormSubmissionFactory;
use RoundlyConsulting\Forms\Enums\SubmissionStatus;
use RoundlyConsulting\Forms\Support\FormModel;
use RoundlyConsulting\Forms\Support\SubmissionModel;

/**
 * Aggregate representing one whole submission: the parent row that groups the
 * per-field {@see Submission} rows sharing a `uuid`. It carries the submission's
 * lifecycle status and acts as the subject routed through the approvals engine.
 *
 * @property int $id
 * @property string $uuid
 * @property int|string|null $sender_id
 * @property string|null $sender_type
 * @property int $form_id
 * @property SubmissionStatus $status
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property CarbonInterface|null $deleted_at
 * @property-read Form $form
 */
class FormSubmission extends Model implements RequiresApprovalInterface
{
    /** @use HasFactory<FormSubmissionFactory> */
    use HasFactory;

    use RequiresApproval;
    use SoftDeletes;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === SubmissionStatus::Draft;
    }

    /**
     * Whether the aggregate is awaiting sign-off. Status-driven so it reflects
     * the reconciled submission lifecycle rather than the raw approval request.
     */
    public function isPendingApproval(): bool
    {
        return $this->status === SubmissionStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === SubmissionStatus::Approved;
    }

    public function isRejected(): bool
    {
        return $this->status === SubmissionStatus::Rejected;
    }

    /**
     * @param  Builder<FormSubmission>  $query
     * @return Builder<FormSubmission>
     */
    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->where('status', SubmissionStatus::Pending->value);
    }

    /** @return MorphTo<Model, $this> */
    public function sender(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The submitted form — a soft-deleted one included, so its past submissions stay
     * readable.
     *
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(FormModel::class(), 'form_id')->withTrashed();
    }

    /** @return HasMany<Submission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(SubmissionModel::class(), 'form_submission_id');
    }

    protected static function newFactory(): FormSubmissionFactory
    {
        return FormSubmissionFactory::new();
    }
}
