<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Concerns;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\MediaLibrary\Buckets\MediaBucket;
use RoundlyConsulting\MediaLibrary\Concerns\InteractsWithMedia;
use RoundlyConsulting\MediaLibrary\Models\Media;

/**
 * First-class media for a per-field {@see Submission}
 * row, built on roundly-consulting/media-library-for-laravel.
 *
 * File-type fields attach their uploaded file to the submission row's own
 * `attachment` bucket (image variants generated for images, other files pass
 * through) instead of the bare disk path the removed FileResolver used. The
 * bucket defaults to a private disk and reads back via signed/temporary URLs so
 * form uploads are not publicly enumerable.
 *
 * @mixin Model
 */
trait HasSubmissionMedia
{
    use InteractsWithMedia;

    /**
     * Purge a submission row's attachment media when the row is force-deleted,
     * so hard-deleting a submission does not orphan its stored files.
     */
    public static function bootHasSubmissionMedia(): void
    {
        static::forceDeleted(function (Model $model): void {
            if ($model instanceof Submission) {
                $model->clearMediaBucket($model->attachmentBucket());
            }
        });
    }

    public function registerMediaBuckets(): void
    {
        $bucket = $this->addMediaBucket($this->attachmentBucket())
            ->withVisibility($this->attachmentVisibility());

        $accepted = config('forms.media.accepted_mime_types');

        if (is_array($accepted) && $accepted !== []) {
            $bucket->acceptsMimeTypes($this->stringList($accepted));
        }

        $maxFileSize = config('forms.media.max_file_size');

        if (is_int($maxFileSize) && $maxFileSize > 0) {
            $bucket->maxFileSize($maxFileSize);
        }

        $this->configureMediaBucket($bucket);
    }

    /**
     * Every file attached to this submission row, in bucket order.
     *
     * @return Collection<int, Media>
     */
    public function attachments(): Collection
    {
        return $this->getMedia($this->attachmentBucket());
    }

    public function hasAttachments(): bool
    {
        return $this->hasMedia($this->attachmentBucket());
    }

    /**
     * The public/base URL of the first attachment, or null when none is stored.
     */
    public function attachmentUrl(string $variant = ''): ?string
    {
        $media = $this->getFirstMedia($this->attachmentBucket());

        return $media?->getUrl($variant);
    }

    /**
     * The base URLs of every attachment (responsive variant when requested).
     *
     * @return list<string>
     */
    public function attachmentUrls(string $variant = ''): array
    {
        return array_values(
            $this->attachments()
                ->map(fn (Media $media): string => $media->getUrl($variant))
                ->all(),
        );
    }

    /**
     * A short-lived signed URL for the first attachment (presigned on capable
     * disks, otherwise via media's signed streaming route).
     */
    public function attachmentTemporaryUrl(string $variant = ''): ?string
    {
        $media = $this->getFirstMedia($this->attachmentBucket());

        return $media?->getTemporaryUrl($this->temporaryUrlExpiry(), $variant);
    }

    public function attachmentBucket(): string
    {
        return (string) config('forms.media.bucket', 'attachment');
    }

    private function attachmentVisibility(): string
    {
        $visibility = config('forms.media.visibility', 'private');

        return $visibility === 'public' ? 'public' : 'private';
    }

    private function configureMediaBucket(MediaBucket $bucket): MediaBucket
    {
        $disk = config('forms.media.disk');

        if (is_string($disk) && $disk !== '') {
            $bucket->useDisk($disk);
        }

        $widths = config('forms.media.responsive_widths');

        $bucket->responsiveWidths(
            is_array($widths) ? $this->normalizeWidths($widths) : null,
        );

        return $bucket;
    }

    private function temporaryUrlExpiry(): DateTimeInterface
    {
        $minutes = config('forms.media.temporary_url_lifetime');

        if (! is_numeric($minutes)) {
            $minutes = config('media.temporary_url_default_lifetime', 5);
        }

        return CarbonImmutable::now()->addMinutes(is_numeric($minutes) ? (int) $minutes : 5);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return list<string>
     */
    private function stringList(array $values): array
    {
        $clean = [];

        foreach ($values as $value) {
            if (is_string($value) && $value !== '') {
                $clean[] = $value;
            }
        }

        return $clean;
    }

    /**
     * @param  array<array-key, mixed>  $widths
     * @return list<int>
     */
    private function normalizeWidths(array $widths): array
    {
        $clean = [];

        foreach ($widths as $width) {
            if (is_int($width) && $width > 0) {
                $clean[] = $width;
            }
        }

        return $clean;
    }
}
