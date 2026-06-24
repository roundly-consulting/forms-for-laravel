<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Resolvers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Submission;

/**
 * Stores an uploaded file on a configured filesystem disk and persists the
 * stored path as the submission value. Reading back returns the path, with a
 * helper to resolve the public URL.
 */
class FileResolver implements Resolver
{
    public function __construct(protected Field $field) {}

    /** @return array<array-key, mixed> */
    public function toStorable(Request $request, ?Model $sender = null): array
    {
        $file = $request->file($this->field->path());

        if (! $file instanceof UploadedFile) {
            return ['value' => null, 'disk' => $this->disk()];
        }

        $path = $file->store($this->directory(), $this->disk());

        return [
            'value' => $path === false ? null : $path,
            'disk' => $this->disk(),
        ];
    }

    public function fromStorage(?Model $sender = null): mixed
    {
        if ($this->field->relationLoaded('submissions')) {
            /** @var array<array-key, mixed>|null $value */
            $value = $this->field->submissions
                ->when(
                    ! is_null($sender),
                    fn (Collection $submissions): Collection => $submissions->filter(
                        fn (Submission $submission): bool => $submission->sender()->is($sender),
                    ),
                )
                ->firstWhere(fn (Submission $submission): bool => $submission->field_id === $this->field->getKey())
                ?->value;
        } else {
            /** @var array<array-key, mixed>|null $value */
            $value = $this->field->submissions()
                ->when(! is_null($sender), fn (Builder $query) => $query->whereMorphedTo('sender', $sender))
                ->where('field_id', $this->field->getKey())
                ->value('value');
        }

        return $value['value'] ?? null;
    }

    public function url(?Model $sender = null): ?string
    {
        $path = $this->fromStorage($sender);

        if (! is_string($path) || $path === '') {
            return null;
        }

        return Storage::disk($this->disk())->url($path);
    }

    private function disk(): string
    {
        /** @var string $disk */
        $disk = config('forms.file.disk', config('filesystems.default', 'local'));

        return $disk;
    }

    private function directory(): string
    {
        /** @var string $directory */
        $directory = config('forms.file.directory', 'form-uploads');

        return $directory;
    }
}
