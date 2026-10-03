<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Concerns;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Support\FormsConfig;
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

        $accepted = FormsConfig::acceptedMimeTypes();

        if ($accepted !== []) {
            $bucket->acceptsMimeTypes($accepted);
        }

        $maxFileSize = FormsConfig::maxFileSize();

        if ($maxFileSize !== null) {
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
     * The URL of the first attachment, resolved by its visibility (see
     * {@see self::resolveAttachmentUrl()}), or null when none is stored.
     */
    public function attachmentUrl(string $variant = ''): ?string
    {
        $media = $this->getFirstMedia($this->attachmentBucket());

        return $media === null ? null : $this->resolveAttachmentUrl($media, $variant);
    }

    /**
     * The URLs of every attachment (responsive variant when requested), each resolved by its
     * visibility (see {@see self::resolveAttachmentUrl()}).
     *
     * @return list<string>
     */
    public function attachmentUrls(string $variant = ''): array
    {
        return array_values(
            $this->attachments()
                ->map(fn (Media $media): string => $this->resolveAttachmentUrl($media, $variant))
                ->all(),
        );
    }

    /**
     * The URL to serve an attachment at, chosen by the attachment's own visibility: a public
     * upload gets its public (CDN-rewritable) URL, a private one a short-lived signed URL
     * (presigned on capable disks, otherwise media's signed streaming route). A private upload
     * never gets a public URL.
     */
    public function resolveAttachmentUrl(Media $media, string $variant = ''): string
    {
        return $media->isPrivate()
            ? $media->getTemporaryUrl($this->temporaryUrlExpiry(), $variant)
            : $media->getUrl($variant);
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
        return FormsConfig::bucket();
    }

    private function attachmentVisibility(): string
    {
        return FormsConfig::visibility();
    }

    private function configureMediaBucket(MediaBucket $bucket): MediaBucket
    {
        $disk = FormsConfig::disk();

        if ($disk !== null) {
            $bucket->useDisk($disk);
        } elseif ($this->attachmentVisibility() === FormsConfig::VISIBILITY_PRIVATE) {
            // A private upload must not land on media-library's default disk: that is the
            // web-served `public` disk, where the file is reachable under /storage without the
            // signed URL. Its variants follow it, whatever `media.variants_disk` says.
            $privateDisk = FormsConfig::privateDisk();

            $bucket->useDisk($privateDisk)->storingVariantsOnDisk($privateDisk);
        }

        $bucket->responsiveWidths(FormsConfig::responsiveWidths());

        return $bucket;
    }

    private function temporaryUrlExpiry(): DateTimeInterface
    {
        return CarbonImmutable::now()->addMinutes(FormsConfig::temporaryUrlLifetime());
    }
}
