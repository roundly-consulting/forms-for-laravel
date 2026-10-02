<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Models;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Forms\Autofill\Autofill;
use RoundlyConsulting\Forms\Concerns\ReleasesKeyWhenTrashed;
use RoundlyConsulting\Forms\Database\Factories\FieldFactory;
use RoundlyConsulting\Forms\Events\FieldCreated;
use RoundlyConsulting\Forms\Events\FieldDeleted;
use RoundlyConsulting\Forms\Events\FieldUpdated;
use RoundlyConsulting\Forms\Exceptions\UnresolvableFieldException;
use RoundlyConsulting\Forms\Resolvers\Resolver;
use RoundlyConsulting\Forms\Support\FieldModel;
use RoundlyConsulting\Forms\Support\FormModel;
use RoundlyConsulting\Forms\Support\GroupModel;
use RoundlyConsulting\Forms\Support\SubmissionModel;
use Throwable;
use UnexpectedValueException;

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
 * @property int $deleted_token 0 while live, the row's id once soft-deleted (frees its key)
 * @property-read Form $form
 * @property-read Group $group
 */
class Field extends Model
{
    /** @use HasFactory<FieldFactory> */
    use HasFactory;

    use ReleasesKeyWhenTrashed;
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
     * Condition references already resolved to a request path, per reference.
     *
     * @var array<string, string>
     */
    private array $conditionPaths = [];

    /**
     * Decide whether this field is visible for the given request payload by
     * evaluating its stored conditions. A field with no conditions is always
     * visible. Conditions are matched against the dotted form path of the
     * referenced field, which may live in any group of the same form (see
     * {@see self::conditionPath()}).
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

            $actual = data_get($input, $this->conditionPath($field));

            if (! $this->matchesCondition($actual, $condition)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The request path of the field a condition references. `group.field` names it
     * exactly; a bare `field` key is looked up in this field's own group first, then in
     * the form's other groups (the first by group order), so a later step can depend on
     * an earlier one. A key no live field of the form carries resolves inside this
     * field's own group, where it reads as absent.
     */
    private function conditionPath(string $reference): string
    {
        return $this->conditionPaths[$reference] ??= $this->resolveConditionPath($reference);
    }

    private function resolveConditionPath(string $reference): string
    {
        if (str_contains($reference, '.')) {
            return $this->form->key.'.'.$reference;
        }

        $fieldModel = FieldModel::class();

        $target = $fieldModel::query()
            ->where('form_id', $this->form_id)
            ->where('key', $reference)
            ->with('group')
            ->get()
            ->reject(fn (Field $field): bool => $field->group->trashed())
            ->sortBy([
                fn (Field $a, Field $b): int => ((string) $b->group_id === (string) $this->group_id) <=> ((string) $a->group_id === (string) $this->group_id),
                fn (Field $a, Field $b): int => $a->group->order <=> $b->group->order,
                fn (Field $a, Field $b): int => $a->group_id <=> $b->group_id,
            ])
            ->first();

        $group = $target instanceof Field ? $target->group : $this->group;

        return implode('.', [$this->form->key, $group->key, $reference]);
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

    /**
     * The attributes {@see AttributeType} this field's stored value maps to,
     * driven by the `forms.field_types` map. Unmapped field types fall back to
     * a plain string, preserving the historical raw-value behaviour.
     */
    public function attributeType(): AttributeType
    {
        /** @var array<string, string> $map */
        $map = config('forms.field_types', []);

        $mapped = $map[$this->type] ?? null;

        if (is_string($mapped)) {
            return AttributeType::tryFrom($mapped) ?? AttributeType::String_;
        }

        return AttributeType::String_;
    }

    /**
     * Cast a raw stored value to the real PHP type mapped from this field, using
     * attributes-for-laravel as the type system for the common string-column
     * case and a defensive cast for already-typed JSON values.
     *
     * A value that does not convert cleanly — a date that doesn't parse, a number that
     * isn't one, a decimal in an integer field, a malformed JSON list — is handed back
     * exactly as stored rather than coerced (`'abc'` never reads as `0`) or thrown, so
     * one bad answer never breaks reading every other submission of the form.
     */
    public function castStoredValue(mixed $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        $type = $this->attributeType();

        if ($type === AttributeType::String_) {
            return $raw;
        }

        try {
            return $this->convertStoredValue($type, $raw);
        } catch (Throwable) {
            return $raw;
        }
    }

    /**
     * @throws UnexpectedValueException when the value does not convert to `$type`
     */
    private function convertStoredValue(AttributeType $type, mixed $raw): mixed
    {
        return match ($type) {
            AttributeType::Integer => match (true) {
                is_int($raw) => $raw,
                is_float($raw) && floor($raw) === $raw => (int) $raw,
                is_string($raw) && filter_var($raw, FILTER_VALIDATE_INT) !== false => (int) $raw,
                default => throw new UnexpectedValueException('not an integer'),
            },
            AttributeType::Float_ => is_int($raw) || is_float($raw) || (is_string($raw) && is_numeric($raw))
                ? (float) $raw
                : throw new UnexpectedValueException('not a number'),
            AttributeType::Boolean => is_bool($raw)
                ? $raw
                : (is_scalar($raw) ? filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null)
                    ?? throw new UnexpectedValueException('not a boolean'),
            AttributeType::Array_ => match (true) {
                is_array($raw) => $raw,
                is_string($raw) && json_validate($raw) && is_array(json_decode($raw, true)) => $type->fromStorage($raw),
                default => throw new UnexpectedValueException('not a list'),
            },
            AttributeType::DateTime => match (true) {
                $raw instanceof DateTimeInterface => Carbon::instance($raw)->toImmutable(),
                is_string($raw) => $type->fromStorage($raw),
                default => throw new UnexpectedValueException('not a date'),
            },
            AttributeType::String_ => $raw,
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

    /**
     * The owning form — a soft-deleted one included: the field (and every answer stored
     * against it) still belongs to it.
     *
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(FormModel::class(), 'form_id')->withTrashed();
    }

    /**
     * The owning group — a soft-deleted one included.
     *
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(GroupModel::class(), 'group_id')->withTrashed();
    }

    /** @return HasMany<Submission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(SubmissionModel::class(), 'field_id');
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
