<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RoundlyConsulting\Forms\Actions\FindFormAction;
use RoundlyConsulting\Forms\Actions\StoreSubmissionAction;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Resolvers\MediaFileResolver;

/**
 * Uploads are private by default (`forms.media.visibility`), and a private upload is only
 * ever linked through a short-lived signed URL. `attachmentUrl()`, `attachmentUrls()` and the
 * resolver's `url()` used to ask media for the PUBLIC URL, which media refuses for private
 * media — so on the default config every one of them threw `MediaCannotBeStreamed`.
 */
beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    $this->freezeSecond();

    $form = Form::factory()->public()->create(['key' => 'kyc']);
    $group = Group::factory()->for($form)->create(['key' => 'docs']);
    Field::factory()->for($form)->for($group)->create(['key' => 'passport', 'type' => 'file']);
});

function storePrivateUpload(?UploadedFile $file = null): Submission
{
    app(StoreSubmissionAction::class)->execute(
        app(FindFormAction::class)->execute('kyc'),
        Request::create('t', files: ['kyc' => ['docs' => ['passport' => $file ?? UploadedFile::fake()->create('p.pdf', 12, 'application/pdf')]]]),
    );

    return Submission::query()->sole();
}

it('keeps uploads private by default', function (): void {
    $submission = storePrivateUpload();

    expect(config('forms.media.visibility'))->toBe('private')
        ->and($submission->attachments()->first()?->isPrivate())->toBeTrue();
});

it('links a private upload through its signed url', function (): void {
    $submission = storePrivateUpload();
    $media = $submission->attachments()->sole();
    $public = Storage::disk('public')->url($media->getPath());

    expect($submission->attachmentUrl())->toBe($submission->attachmentTemporaryUrl())
        ->and($submission->attachmentUrls())->toBe([$submission->attachmentTemporaryUrl()])
        ->and($submission->resolveAttachmentUrl($media))->toBe($submission->attachmentTemporaryUrl())
        ->and($submission->attachmentUrl())->not->toBe($public);
});

it('returns the signed url from the resolver for a private upload', function (): void {
    $submission = storePrivateUpload();

    /** @var MediaFileResolver $resolver */
    $resolver = Field::query()->where('key', 'passport')->sole()->resolver();

    expect($resolver->url())->toBe($submission->attachmentTemporaryUrl());
});

it('keeps the public url for a public upload', function (): void {
    config()->set('forms.media.visibility', 'public');

    $submission = storePrivateUpload();
    $media = $submission->attachments()->sole();

    expect($media->isPrivate())->toBeFalse()
        ->and($submission->attachmentUrl())->toBe($media->getUrl())
        ->and($submission->attachmentUrls())->toBe([$media->getUrl()]);
});

/*
 * Signed URLs only protect the link. Private uploads used to be written to media-library's
 * default disk — the web-served `public` disk — so the file itself sat under /storage for anyone
 * with the path (the trait's docblock already promised "a private disk"). They now default to
 * `forms.media.private_disk` ('local').
 */

it('stores private uploads and their variants on the private disk', function (): void {
    config()->set('media.variants_disk', 'public');

    $media = storePrivateUpload(UploadedFile::fake()->image('p.jpg', 800, 600))->attachments()->sole();

    expect($media->disk)->toBe('local')
        ->and($media->diskFor('responsive-320'))->toBe('local')
        ->and(Storage::disk('local')->exists($media->getPath()))->toBeTrue()
        ->and(Storage::disk('local')->exists($media->getPath('responsive-320')))->toBeTrue()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('honours a configured private disk and an explicit disk', function (): void {
    config()->set('filesystems.disks.vault', ['driver' => 'local', 'root' => storage_path('app/vault')]);
    Storage::fake('vault');
    config()->set('forms.media.private_disk', 'vault');

    expect(storePrivateUpload()->attachments()->sole()->disk)->toBe('vault');

    Submission::query()->forceDelete();
    config()->set('forms.media.disk', 'public');

    expect(storePrivateUpload()->attachments()->sole()->disk)->toBe('public');
});

it('keeps public uploads on the media default disk', function (): void {
    config()->set('forms.media.visibility', 'public');

    expect(storePrivateUpload()->attachments()->sole()->disk)->toBe('public');
});

it('serves a private upload from the real private disk through the signed stream route', function (): void {
    // A real (not faked) local disk: no native temporary URLs, so media mints its own signed
    // streaming route — the path a default host's private uploads take. The route must stream
    // the bytes from the private disk, and refuse the link once it is tampered with.
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    $root = sys_get_temp_dir().'/forms-private-'.bin2hex(random_bytes(4));
    config()->set('filesystems.disks.local', ['driver' => 'local', 'root' => $root]);
    Storage::forgetDisk('local');

    try {
        $submission = storePrivateUpload(UploadedFile::fake()->createWithContent('p.txt', 'passport bytes'));
        $media = $submission->attachments()->sole();
        $url = (string) $submission->attachmentUrl();

        expect($media->disk)->toBe('local')
            ->and(is_file($root.'/'.$media->getPath()))->toBeTrue()
            ->and(Storage::disk('public')->exists($media->getPath()))->toBeFalse()
            ->and(URL::hasValidSignature(Request::create($url)))->toBeTrue();

        $response = $this->get($url);

        $response->assertOk();
        expect($response->streamedContent())->toBe('passport bytes');

        $this->get($url.'0')->assertForbidden();
    } finally {
        Storage::forgetDisk('local');
        File::deleteDirectory($root);
    }
});
