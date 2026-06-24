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
use RoundlyConsulting\Forms\Autofill\Autofill;
use RoundlyConsulting\Forms\Database\Factories\FieldFactory;
use RoundlyConsulting\Forms\Events\FieldCreated;
use RoundlyConsulting\Forms\Events\FieldDeleted;
use RoundlyConsulting\Forms\Events\FieldUpdated;
use RoundlyConsulting\Forms\Exceptions\UnresolvableFieldException;
use RoundlyConsulting\Forms\Resolvers\Resolver;

/**
 * @property int $id
 * @property int $form_id
 * @property int $group_id
 * @property string $name
 * @property string $key
 * @property string|null $help
 * @property string $type
 * @property string|null $autofill
 * @property array<array-key, mixed>|null $options
 * @property array<array-key, mixed>|null $validations
 * @property array<array-key, mixed>|null $conditions
 * @property array<array-key, mixed>|null $messages
 * @property int $order
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property CarbonInterface|null $deleted_at
 * @property-read Form $form
 * @property-read Group $group
 */
class Field extends Model
{
    /** @use HasFactory<FieldFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    /** @var array<string, class-string> */
    protected $dispatchesEvents = [
        'created' => FieldCreated::class,
        'updated' => FieldUpdated::class,
        'deleted' => FieldDeleted::class,
    ];

    /**
     * @param  Builder<Field>  $query
     * @return Builder<Field>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->oldest('order');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'validations' => 'array',
            'conditions' => 'array',
            'messages' => 'array',
            'order' => 'int',
        ];
    }

    public function isRequired(): bool
    {
        foreach ($this->validations ?: [] as $rule) {
            if (is_string($rule) && ($rule === 'required' || str_starts_with($rule, 'required'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Decide whether this field is visible for the given request payload by
     * evaluating its stored conditions. A field with no conditions is always
     * visible. Conditions are matched against the dotted form path of the
     * referenced field.
     *
     * @param  array<array-key, mixed>  $input
     */
    public function isVisible(array $input): bool
    {
        $conditions = $this->conditions ?: [];

        if ($conditions === []) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (! is_array($condition)) {
                continue;
            }

            /** @var array<string, mixed> $condition */
            $field = isset($condition['field']) ? (string) $condition['field'] : null;

            if ($field === null) {
                continue;
            }

            $path = implode('.', [$this->form->key, $this->group->key, $field]);
            $actual = data_get($input, $path);

            if (! $this->matchesCondition($actual, $condition)) {
                return false;
            }
        }

        return true;
    }

    /** @param  array<string, mixed>  $condition */
    private function matchesCondition(mixed $actual, array $condition): bool
    {
        $operator = isset($condition['operator']) ? (string) $condition['operator'] : '=';
        $expected = $condition['value'] ?? null;

        return match ($operator) {
            '!=' => $actual != $expected,
            'in' => is_array($expected) && in_array($actual, $expected, false),
            'not_in' => is_array($expected) && ! in_array($actual, $expected, false),
            'filled' => filled($actual),
            'empty' => blank($actual),
            default => $actual == $expected,
        };
    }

    public function resolver(): Resolver
    {
        /** @var array<string, class-string<Resolver>> $resolvers */
        $resolvers = config('forms.fields', []);

        $resolver = $resolvers[$this->type] ?? $resolvers['default'] ?? null;

        if ($resolver === null) {
            throw UnresolvableFieldException::forType($this->type);
        }

        return new $resolver($this);
    }

    public function path(): string
    {
        return implode('.', [
            $this->form->key,
            $this->group->key,
            $this->key,
        ]);
    }

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        /** @var class-string<Form> $form */
        $form = config('forms.models.form', Form::class);

        return $this->belongsTo($form);
    }

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        /** @var class-string<Group> $group */
        $group = config('forms.models.group', Group::class);

        return $this->belongsTo($group);
    }

    /** @return HasMany<Submission, $this> */
    public function submissions(): HasMany
    {
        /** @var class-string<Submission> $submission */
        $submission = config('forms.models.submission', Submission::class);

        return $this->hasMany($submission);
    }

    public function hasOptions(): bool
    {
        return count($this->options ?: []) > 0;
    }

    public function hasValidations(): bool
    {
        return count($this->validations ?: []) > 0;
    }

    public function getAutofillValue(): mixed
    {
        if (filled($this->autofill) && class_exists($this->autofill)) {
            $autofill = resolve($this->autofill);

            if ($autofill instanceof Autofill) {
                return $autofill->fill($this);
            }
        }

        return $this->autofill;
    }

    protected static function newFactory(): FieldFactory
    {
        return FieldFactory::new();
    }
}
