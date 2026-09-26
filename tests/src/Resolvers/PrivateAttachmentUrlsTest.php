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
    $this->freezeSecond();

    $form = Form::factory()->public()->create(['key' => 'kyc']);
    $group = Group::factory()->for($form)->create(['key' => 'docs']);
    Field::factory()->for($form)->for($group)->create(['key' => 'passport', 'type' => 'file']);
});

function storePrivateUpload(): Submission
{
    app(StoreSubmissionAction::class)->execute(
        app(FindFormAction::class)->execute('kyc'),
        Request::create('t', files: ['kyc' => ['docs' => ['passport' => UploadedFile::fake()->create('p.pdf', 12, 'application/pdf')]]]),
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
    $public = Storage::disk($media->disk)->url($media->getPath());

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

it('streams a private upload through a valid signed route on a plain local disk', function (): void {
    // A real (not faked) local disk has no native temporary URLs, so media degrades to its
    // signed streaming route — the path a default host's private uploads actually take.
    $root = sys_get_temp_dir().'/forms-private-'.bin2hex(random_bytes(4));
    config()->set('filesystems.disks.forms-private', ['driver' => 'local', 'root' => $root]);
    config()->set('forms.media.disk', 'forms-private');

    try {
        $url = (string) storePrivateUpload()->attachmentUrl();

        expect(URL::hasValidSignature(Request::create($url)))->toBeTrue();
    } finally {
        File::deleteDirectory($root);
    }
});
