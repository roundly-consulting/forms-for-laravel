<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Forms\Actions\FindFormAction;
use RoundlyConsulting\Forms\Actions\StoreSubmissionAction;
use RoundlyConsulting\Forms\Actions\SyncFormsAction;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Resolvers\DefaultResolver;
use RoundlyConsulting\Forms\Support\FormsConfig;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/**
 * Sweep 2 — the non-boolean settings. A `media.visibility` typo quietly became private, a junk
 * bucket or disk fell back, a junk size or lifetime was ignored, and a resolver map entry that
 * was not a Resolver blew up as a bare Error. Each now throws a config error naming the key.
 *
 * Sweep 3 — a blank value (a host's `KEY=`, or whitespace) is not set: it takes the default, or
 * for an optional setting or a map entry none, exactly like an absent key. Junk still throws.
 */
beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
});

function strictSubmission(): Submission
{
    return Submission::factory()->make();
}

function strictStoredUpload(): Submission
{
    $form = Form::factory()->public()->create(['key' => 'kyc']);
    $group = Group::factory()->for($form)->create(['key' => 'docs']);
    Field::factory()->for($form)->for($group)->create(['key' => 'passport', 'type' => 'file']);

    app(StoreSubmissionAction::class)->execute(
        app(FindFormAction::class)->execute('kyc'),
        Request::create('t', files: ['kyc' => ['docs' => ['passport' => UploadedFile::fake()->image('p.jpg', 400, 300)]]]),
    );

    return Submission::query()->sole();
}

it('refuses an upload visibility typo (strict config)', function (mixed $value): void {
    config()->set('forms.media.visibility', $value);

    expect(fn () => strictSubmission()->resolveMediaBucket('attachment'))->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [forms.media.visibility] must be one of [private, public]',
    );
})->with(['typo' => ['publik'], 'capitalised' => ['Public']]);

it('keeps uploads private when the visibility is absent or blank (strict config)', function (?string $value): void {
    config()->set('forms.media.visibility', $value);

    expect(strictSubmission()->resolveMediaBucket('attachment')?->getVisibility())->toBe('private');
})->with(['absent' => [null], 'blank' => [''], 'whitespace' => [' ']]);

it('refuses a non-string media storage setting (strict config)', function (string $key, mixed $value): void {
    config()->set($key, $value);

    expect(fn () => strictSubmission()->resolveMediaBucket('attachment'))
        ->toThrow(InvalidConfigurationException::class, "Configuration value [{$key}] must be a non-empty string");
})->with([
    'bucket array' => ['forms.media.bucket', ['a']],
    'disk int' => ['forms.media.disk', 1],
    'private disk int' => ['forms.media.private_disk', 3],
]);

it('reads a blank media storage setting as not set (strict config)', function (): void {
    config()->set('forms.media.bucket', '');
    config()->set('forms.media.disk', ' ');
    config()->set('forms.media.private_disk', '');

    expect(FormsConfig::bucket())->toBe('attachment')
        ->and(FormsConfig::disk())->toBeNull()
        ->and(FormsConfig::privateDisk())->toBe('local')
        ->and(strictSubmission()->resolveMediaBucket('attachment')?->getDisk())->toBe('local');
});

it('reads a blank optional media, map or definitions setting as not set (strict config)', function (): void {
    config()->set('forms.media.accepted_mime_types', '');
    config()->set('forms.media.max_file_size', ' ');
    config()->set('forms.media.responsive_widths', '');
    config()->set('forms.media.temporary_url_lifetime', '');
    config()->set('media.temporary_url_default_lifetime', 8);
    config()->set('forms.field_types', '');
    config()->set('forms.fields', ' ');
    config()->set('forms.definitions', '');

    expect(FormsConfig::acceptedMimeTypes())->toBe([])
        ->and(FormsConfig::maxFileSize())->toBeNull()
        ->and(FormsConfig::responsiveWidths())->toBeNull()
        ->and(FormsConfig::configuredTemporaryUrlLifetime())->toBeNull()
        ->and(FormsConfig::temporaryUrlLifetime())->toBe(8)
        ->and(FormsConfig::fieldTypeMap())->toBe([])
        ->and(FormsConfig::resolverMap())->toBe([])
        ->and(FormsConfig::definitions())->toBe([]);
});

it('reads a blank field type or resolver entry as unmapped (strict config)', function (): void {
    config()->set('forms.field_types', ['amount' => '']);
    config()->set('forms.fields', ['custom' => ' ', 'default' => DefaultResolver::class]);

    expect(FormsConfig::fieldType('amount'))->toBe(AttributeType::String_)
        ->and(FormsConfig::resolver('custom'))->toBe(DefaultResolver::class);

    config()->set('forms.fields', ['default' => '']);

    expect(FormsConfig::resolver('custom'))->toBeNull();
});

it('refuses a junk media list or size (strict config)', function (string $key, mixed $value): void {
    config()->set($key, $value);

    expect(fn () => strictSubmission()->resolveMediaBucket('attachment'))
        ->toThrow(InvalidConfigurationException::class, $key);
})->with([
    'mime types string' => ['forms.media.accepted_mime_types', 'image/png'],
    'mime types junk entry' => ['forms.media.accepted_mime_types', ['image/png', 9]],
    'max file size junk' => ['forms.media.max_file_size', '10MB'],
    'max file size zero' => ['forms.media.max_file_size', 0],
    'widths string' => ['forms.media.responsive_widths', '320'],
    'widths junk entry' => ['forms.media.responsive_widths', [320, 'wide']],
]);

it('reads integer strings for the upload size and widths (strict config)', function (): void {
    config()->set('forms.media.max_file_size', '4096');
    config()->set('forms.media.responsive_widths', ['320', 640]);

    $bucket = strictSubmission()->resolveMediaBucket('attachment');

    expect($bucket?->getMaxFileSize())->toBe(4096)
        ->and($bucket?->getResponsiveWidths())->toBe([320, 640]);
});

it('refuses a junk signed-url lifetime (strict config)', function (string $key, mixed $value): void {
    $submission = strictStoredUpload();
    config()->set($key, $value);

    expect(fn () => $submission->attachmentTemporaryUrl())->toThrow(InvalidConfigurationException::class, $key);
})->with([
    'forms lifetime junk' => ['forms.media.temporary_url_lifetime', 'soon'],
    'forms lifetime zero' => ['forms.media.temporary_url_lifetime', 0],
    'media default junk' => ['media.temporary_url_default_lifetime', 'soon'],
]);

it('refuses a resolver map entry that is not a Resolver (strict config)', function (mixed $value): void {
    config()->set('forms.fields.custom', $value);

    $field = Field::factory()->make(['type' => 'custom']);

    expect(fn () => $field->resolver())->toThrow(InvalidConfigurationException::class, 'forms.fields.custom');
})->with([
    'unknown class' => ['App\\Missing\\Resolver'],
    'not a resolver' => [stdClass::class],
    'a number' => [5],
]);

it('refuses a resolver map that is not an array (strict config)', function (): void {
    config()->set('forms.fields', DefaultResolver::class);

    expect(fn () => Field::factory()->make(['type' => 'text'])->resolver())
        ->toThrow(InvalidConfigurationException::class, 'forms.fields');
});

it('refuses form definitions that are not a list of arrays (strict config)', function (mixed $value): void {
    config()->set('forms.definitions', $value);

    expect(fn () => app(SyncFormsAction::class)->execute())
        ->toThrow(InvalidConfigurationException::class, 'forms.definitions');
})->with([
    'a string' => ['contact'],
    'a scalar entry' => [['contact']],
]);

it('flags a broken setting in about instead of rendering a fallback (strict config)', function (): void {
    config()->set('forms.media.visibility', 'publik');
    config()->set('forms.media.max_file_size', '10MB');
    config()->set('forms.media.temporary_url_lifetime', 'soon');

    Artisan::call('about', ['--only' => 'forms']);
    $output = Artisan::output();

    expect($output)->toMatch('/Attachments\W+INVALID/')
        ->and($output)->toMatch('/Max upload size\W+INVALID/')
        ->and($output)->toMatch('/Signed URL lifetime\W+INVALID/');
});
