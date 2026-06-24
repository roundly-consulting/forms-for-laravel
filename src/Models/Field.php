<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RoundlyConsulting\Forms\Autofill\Autofill;
use RoundlyConsulting\Forms\Database\Factories\FieldFactory;
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
 * @property int $order
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Form $form
 * @property-read Group $group
 */
class Field extends Model
{
    /** @use HasFactory<FieldFactory> */
    use HasFactory;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'validations' => 'array',
            'order' => 'int',
        ];
    }

    public function resolver(): Resolver
    {
        /** @var array<string, class-string<Resolver>> $resolvers */
        $resolvers = config('forms.fields');
        $resolver = $resolvers['default'];

        if (array_key_exists($this->type, $resolvers)) {
            $resolver = $resolvers[$this->type];
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
