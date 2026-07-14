<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Forms\Database\Factories\GroupFactory;
use RoundlyConsulting\Forms\Events\GroupCreated;
use RoundlyConsulting\Forms\Events\GroupDeleted;
use RoundlyConsulting\Forms\Events\GroupUpdated;

/**
 * @property int $id
 * @property int $form_id
 * @property string $name
 * @property string $key
 * @property int $order
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property CarbonInterface|null $deleted_at
 */
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    /** @var array<string, class-string> */
    protected $dispatchesEvents = [
        'created' => GroupCreated::class,
        'updated' => GroupUpdated::class,
        'deleted' => GroupDeleted::class,
    ];

    /**
     * @param  Builder<Group>  $query
     * @return Builder<Group>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->oldest('order');
    }

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        /** @var class-string<Form> $form */
        $form = config('forms.models.form', Form::class);

        return $this->belongsTo($form);
    }

    /** @return HasMany<Field, $this> */
    public function fields(): HasMany
    {
        /** @var class-string<Field> $field */
        $field = config('forms.models.field', Field::class);

        return $this->hasMany($field, 'group_id');
    }

    protected static function newFactory(): GroupFactory
    {
        return GroupFactory::new();
    }
}
