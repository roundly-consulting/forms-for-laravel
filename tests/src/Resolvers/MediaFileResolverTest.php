<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\Forms\Actions\FindFormAction;
use RoundlyConsulting\Forms\Actions\StoreSubmissionAction;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Resolvers\MediaFileResolver;
use RoundlyConsulting\MediaLibrary\Contracts\HasMedia;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    config()->set('forms.media.visibility', 'public');

    $form = Form::factory()->public()->create(['key' => 'kyc']);
    $group = Group::factory()->for($form)->create(['key' => 'docs']);
    Field::factory()->for($form)->for($group)->create(['key' => 'passport', 'type' => 'file']);
});

function uploadRequest(UploadedFile $file): Request
{
    return Request::create('t', files: [
        'kyc' => ['docs' => ['passport' => $file]],
    ]);
}

it('resolves the file field to the media-backed resolver', function () {
    $field = Field::query()->where('key', 'passport')->sole();

    expect($field->resolver())->toBeInstanceOf(MediaFileResolver::class);
});

it('makes the submission row a media owner', function () {
    expect(Submission::factory()->make())->toBeInstanceOf(HasMedia::class);
});

it('attaches an uploaded image as media on the submission row', function () {
    $form = app(FindFormAction::class)->execute('kyc');

    app(StoreSubmissionAction::class)->execute($form, uploadRequest(UploadedFile::fake()->image('p.jpg', 800, 600)));

    /** @var Submission $submission */
    $submission = Submission::query()->sole();

    expect($submission->hasAttachments())->toBeTrue()
        ->and($submission->attachments())->toHaveCount(1)
        ->and($submission->value['value'])->toBe($submission->attachments()->first()?->uuid)
        ->and($submission->attachmentUrl())->toContain($submission->value['value']);
});

it('recognises an image attachment and resolves its url', function () {
    config()->set('forms.media.responsive_widths', [320]);

    $form = app(FindFormAction::class)->execute('kyc');

    app(StoreSubmissionAction::class)->execute($form, uploadRequest(UploadedFile::fake()->image('p.jpg', 800, 600)));

    $media = Submission::query()->sole()->attachments()->first();

    expect($media?->isImage())->toBeTrue()
        ->and((string) $media?->getUrl())->not->toBe('');
});

it('passes a non-image file through', function () {
    $form = app(FindFormAction::class)->execute('kyc');

    app(StoreSubmissionAction::class)->execute($form, uploadRequest(UploadedFile::fake()->create('p.pdf', 12, 'application/pdf')));

    $media = Submission::query()->sole()->attachments()->first();

    expect($media)->not->toBeNull()
        ->and($media?->isImage())->toBeFalse();
});

it('stores a null value when no file is supplied', function () {
    $form = app(FindFormAction::class)->execute('kyc');

    app(StoreSubmissionAction::class)->execute($form, Request::create('t'));

    /** @var Submission $submission */
    $submission = Submission::query()->sole();

    expect($submission->value['value'])->toBeNull()
        ->and($submission->hasAttachments())->toBeFalse();
});

it('reads back the stored uuid and url through the resolver', function () {
    $form = app(FindFormAction::class)->execute('kyc');

    app(StoreSubmissionAction::class)->execute($form, uploadRequest(UploadedFile::fake()->image('p.jpg', 400, 300)));

    $field = Field::query()->where('key', 'passport')->with('submissions')->sole();
    /** @var MediaFileResolver $resolver */
    $resolver = $field->resolver();

    expect($resolver->fromStorage())->toBeString()
        ->and((string) $resolver->url())->not->toBe('');
});

it('returns null from the resolver when nothing is stored', function () {
    $field = Field::query()->where('key', 'passport')->sole();
    /** @var MediaFileResolver $resolver */
    $resolver = $field->resolver();

    expect($resolver->fromStorage())->toBeNull()
        ->and($resolver->url())->toBeNull()
        ->and($resolver->temporaryUrl())->toBeNull();
});

it('mints a signed temporary url for a private attachment', function () {
    config()->set('forms.media.visibility', 'private');

    $form = app(FindFormAction::class)->execute('kyc');

    app(StoreSubmissionAction::class)->execute($form, uploadRequest(UploadedFile::fake()->image('p.jpg', 400, 300)));

    /** @var Submission $submission */
    $submission = Submission::query()->sole();

    expect((string) $submission->attachmentTemporaryUrl())->not->toBe('');
});

it('purges attachment media when the submission is force-deleted', function () {
    $form = app(FindFormAction::class)->execute('kyc');

    app(StoreSubmissionAction::class)->execute($form, uploadRequest(UploadedFile::fake()->image('p.jpg', 400, 300)));

    /** @var Submission $submission */
    $submission = Submission::query()->sole();

    expect($submission->media()->count())->toBe(1);

    $submission->forceDelete();

    expect($submission->media()->count())->toBe(0);
});
