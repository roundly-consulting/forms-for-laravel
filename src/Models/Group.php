<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RoundlyConsulting\Forms\Database\Factories\GroupFactory;

/**
 * @property int $id
 * @property int $form_id
 * @property string $name
 * @property string $key
 * @property int $order
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    protected $guarded = [];

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

        return $this->hasMany($field);
    }

    protected static function newFactory(): GroupFactory
    {
        return GroupFactory::new();
    }
}
