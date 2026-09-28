<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Resolvers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\MediaLibrary\Models\Media;

/**
 * Stores an uploaded file as media on the per-field submission row, built on
 * roundly-consulting/media-library-for-laravel. Images get responsive variants,
 * other files pass through, and the file is served back via the submission
 * row's signed/temporary URL so uploads stay private by default.
 *
 * The uploaded file is attached in {@see MediaFileResolver::attach()} — after
 * the owner row exists — and the media UUID is persisted as the row's value.
 */
class MediaFileResolver implements AttachesToSubmission, Resolver
{
    public function __construct(protected Field $field) {}

    /**
     * The media owner row does not exist yet at resolve time, so the value is
     * seeded null here and filled in {@see MediaFileResolver::attach()} once the
     * row has been created.
     *
     * @return array<array-key, mixed>
     */
    public function toStorable(Request $request, ?Model $sender = null): array
    {
        return ['value' => null];
    }

    public function attach(Submission $submission, Request $request, ?Model $sender = null): void
    {
        $file = $request->file($this->field->path());

        if (! $file instanceof UploadedFile) {
            return;
        }

        $media = $submission->addMedia($file)->toBucket($submission->attachmentBucket());

        $submission->update(['value' => ['value' => $media->uuid]]);
    }

    /**
     * A temporary copy of the row's stored attachment, as the upload it once was — its
     * original name and mime type — so validation checks the real bytes.
     */
    public function restoreUpload(Submission $submission): ?UploadedFile
    {
        $media = $submission->getFirstMedia($submission->attachmentBucket());

        if (! $media instanceof Media) {
            return null;
        }

        $source = $media->getStream();
        $path = tempnam(sys_get_temp_dir(), 'forms-upload-');

        if (! is_resource($source) || $path === false) {
            return null;
        }

        $target = fopen($path, 'wb');

        if ($target === false) {
            return null;
        }

        stream_copy_to_stream($source, $target);
        fclose($target);
        fclose($source);

        return new UploadedFile($path, $media->file_name, $media->mime_type, UPLOAD_ERR_OK, test: true);
    }

    public function fromStorage(?Model $sender = null): mixed
    {
        $value = $this->submissionRow($sender)?->value;

        return is_array($value) ? ($value['value'] ?? null) : null;
    }

    /**
     * The URL of the stored attachment on the owner row — a short-lived signed URL when the
     * upload is private (the default), its public URL when public — or null when none is
     * stored.
     */
    public function url(?Model $sender = null, string $variant = ''): ?string
    {
        return $this->submissionRow($sender)?->attachmentUrl($variant);
    }

    /**
     * A short-lived signed URL for the stored attachment, or null when none is
     * stored.
     */
    public function temporaryUrl(?Model $sender = null, string $variant = ''): ?string
    {
        return $this->submissionRow($sender)?->attachmentTemporaryUrl($variant);
    }

    private function submissionRow(?Model $sender = null): ?Submission
    {
        if ($this->field->relationLoaded('submissions')) {
            return $this->field->submissions
                ->when(
                    ! is_null($sender),
                    fn (Collection $submissions): Collection => $submissions->filter(
                        fn (Submission $submission): bool => $submission->sender()->is($sender),
                    ),
                )
                ->firstWhere(fn (Submission $submission): bool => $submission->field_id === $this->field->getKey());
        }

        return $this->field->submissions()
            ->when(! is_null($sender), fn (Builder $query) => $query->whereMorphedTo('sender', $sender))
            ->where('field_id', $this->field->getKey())
            ->latest('id')
            ->first();
    }
}
