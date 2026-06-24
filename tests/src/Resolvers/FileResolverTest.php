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
use RoundlyConsulting\Forms\Resolvers\FileResolver;

beforeEach(function () {
    config()->set('forms.file.disk', 'uploads');
    config()->set('forms.file.directory', 'docs');
    Storage::fake('uploads');

    $form = Form::factory()->public()->create(['key' => 'kyc']);
    $group = Group::factory()->for($form)->create(['key' => 'docs']);
    Field::factory()->for($form)->for($group)->create(['key' => 'passport', 'type' => 'file']);
});

it('stores an uploaded file and persists its path', function () {
    $form = app(FindFormAction::class)->execute('kyc');

    $request = Request::create('t', files: [
        'kyc' => ['docs' => ['passport' => UploadedFile::fake()->create('p.pdf', 10)]],
    ]);

    app(StoreSubmissionAction::class)->execute($form, $request);

    /** @var Submission $submission */
    $submission = Submission::query()->sole();
    $path = $submission->value['value'];

    expect($path)->toBeString()
        ->and($path)->toStartWith('docs/');
    Storage::disk('uploads')->assertExists($path);
});

it('stores null when no file is supplied', function () {
    $form = app(FindFormAction::class)->execute('kyc');

    app(StoreSubmissionAction::class)->execute($form, Request::create('t'));

    expect(Submission::query()->sole()->value['value'])->toBeNull();
});

it('reads back the stored path and url via the resolver', function () {
    $form = app(FindFormAction::class)->execute('kyc');

    app(StoreSubmissionAction::class)->execute($form, Request::create('t', files: [
        'kyc' => ['docs' => ['passport' => UploadedFile::fake()->create('p.pdf', 10)]],
    ]));

    $field = Field::query()->where('key', 'passport')->with('submissions')->sole();
    /** @var FileResolver $resolver */
    $resolver = $field->resolver();

    expect($resolver)->toBeInstanceOf(FileResolver::class)
        ->and($resolver->fromStorage())->toBeString()
        ->and($resolver->url())->toContain('docs/');
});

it('returns null url when nothing is stored', function () {
    $field = Field::query()->where('key', 'passport')->sole();
    /** @var FileResolver $resolver */
    $resolver = $field->resolver();

    expect($resolver->url())->toBeNull();
});
