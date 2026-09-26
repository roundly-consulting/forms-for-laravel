<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Forms\Database\Factories\FormFactory;
use RoundlyConsulting\Forms\Events\FormCreated;
use RoundlyConsulting\Forms\Events\FormDeleted;
use RoundlyConsulting\Forms\Events\FormUpdated;
use RoundlyConsulting\Forms\Exceptions\FormSubmissionClosedException;
use RoundlyConsulting\Forms\Support\GroupModel;
use RoundlyConsulting\Forms\Support\SubmissionModel;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property CarbonInterface|null $expires_at
 * @property bool $is_public
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property CarbonInterface|null $deleted_at
 */
class Form extends Model
{
    /** @use HasFactory<FormFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    /** @var array<string, class-string> */
    protected $dispatchesEvents = [
        'created' => FormCreated::class,
        'updated' => FormUpdated::class,
        'deleted' => FormDeleted::class,
    ];

    /**
     * @param  Builder<Form>  $query
     * @return Builder<Form>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * @param  Builder<Form>  $query
     * @return Builder<Form>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    /**
     * @param  Builder<Form>  $query
     * @return Builder<Form>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }

    /**
     * @param  Builder<Form>  $query
     * @return Builder<Form>
     */
    public function scopeForKey(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_public' => 'bool',
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isAcceptingSubmissions(): bool
    {
        return $this->is_public && ! $this->isExpired();
    }

    /**
     * The one closed-form rule every path to a final submission enforces — submit, draft,
     * finalize and single-row creation alike.
     *
     * @throws FormSubmissionClosedException when the form is non-public or expired.
     */
    public function ensureAcceptingSubmissions(): void
    {
        if (! $this->isAcceptingSubmissions()) {
            throw FormSubmissionClosedException::forKey($this->key);
        }
    }

    /** @return HasMany<Submission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(SubmissionModel::class(), 'form_id');
    }

    /** @return HasMany<Group, $this> */
    public function groups(): HasMany
    {
        return $this->hasMany(GroupModel::class(), 'form_id');
    }

    /** @return HasManyThrough<Field, Group, $this> */
    public function fields(): HasManyThrough
    {
        return $this->through($this->groups())
            ->has(fn (Group $group) => $group->fields());
    }

    protected static function newFactory(): FormFactory
    {
        return FormFactory::new();
    }
}
