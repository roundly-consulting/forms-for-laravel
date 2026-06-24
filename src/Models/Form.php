<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use RoundlyConsulting\Forms\Database\Factories\FormFactory;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property CarbonInterface|null $expires_at
 * @property bool $is_public
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class Form extends Model
{
    /** @use HasFactory<FormFactory> */
    use HasFactory;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_public' => 'bool',
            'expires_at' => 'datetime',
        ];
    }

    /** @return HasMany<Submission, $this> */
    public function submissions(): HasMany
    {
        /** @var class-string<Submission> $submission */
        $submission = config('forms.models.submission', Submission::class);

        return $this->hasMany($submission);
    }

    /** @return HasMany<Group, $this> */
    public function groups(): HasMany
    {
        /** @var class-string<Group> $group */
        $group = config('forms.models.group', Group::class);

        return $this->hasMany($group);
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
