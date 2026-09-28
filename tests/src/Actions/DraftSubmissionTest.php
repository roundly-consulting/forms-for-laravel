<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RoundlyConsulting\Forms\Enums\SubmissionStatus;
use RoundlyConsulting\Forms\Events\FormSubmitted;
use RoundlyConsulting\Forms\Exceptions\DraftNotFoundException;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\GroupBuilder;
use RoundlyConsulting\Forms\Models\FormSubmission;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Tests\testable\Submitter;
use RoundlyConsulting\MediaLibrary\Models\Media;

function draftForm(): void
{
    Forms::define('apply', 'Application')
        ->public()
        ->group('g', 'G', function (GroupBuilder $g): void {
            $g->field('name', 'Name')->rules(['required']);
            $g->field('email', 'Email')->rules(['required', 'email']);
        })
        ->create();
}

it('saves a partial draft without running validation', function () {
    draftForm();
    $form = Forms::find('apply');

    $result = Forms::draft($form, Request::create('t', parameters: [
        'apply' => ['g' => ['name' => 'Jane']],
    ]));

    expect(Submission::query()->where('uuid', $result->uuid)->draft()->count())->toBe(2)
        ->and(Submission::query()->final()->count())->toBe(0)
        ->and(Submission::query()->draft()->first()?->isDraft())->toBeTrue();
});

it('excludes drafts from final reads but keeps them retrievable', function () {
    draftForm();
    $form = Forms::find('apply');

    Forms::draft($form, Request::create('t', parameters: ['apply' => ['g' => ['name' => 'Jane']]]));

    expect(Submission::query()->final()->count())->toBe(0)
        ->and(Submission::query()->draft()->count())->toBe(2);
});

it('finalizes a draft after full validation passes', function () {
    Event::fake();
    draftForm();
    $form = Forms::find('apply');

    $draft = Forms::draft($form, Request::create('t', parameters: [
        'apply' => ['g' => ['name' => 'Jane', 'email' => 'jane@example.com']],
    ]));

    $result = Forms::finalize($draft->uuid);

    expect($result->uuid)->toBe($draft->uuid)
        ->and(Submission::query()->final()->where('uuid', $draft->uuid)->count())->toBe(2)
        ->and(Submission::query()->draft()->count())->toBe(0);

    Event::assertDispatched(fn (FormSubmitted $e): bool => $e->uuid === $draft->uuid);
});

it('rejects finalizing a draft that fails validation', function () {
    draftForm();
    $form = Forms::find('apply');

    $draft = Forms::draft($form, Request::create('t', parameters: [
        'apply' => ['g' => ['name' => 'Jane']],
    ]));

    expect(fn () => Forms::finalize($draft->uuid))->toThrow(ValidationException::class);

    expect(Submission::query()->draft()->count())->toBe(2);
});

/**
 * Both shapes of "no such draft" must answer the same way on every engine.
 *
 * The malformed case is the one that shipped broken: `submissions.uuid` is a real uuid
 * column, so Postgres refuses to compare 'missing-uuid' against it and raises
 * `SQLSTATE[22P02] invalid input syntax for type uuid` from inside the query — a raw
 * QueryException, not DraftNotFoundException, and never the documented exception a host
 * catches. SQLite stores the column as text, compares happily, matches nothing, and the
 * intended exception fired — which is exactly why 204 green tests never saw it.
 *
 * The well-formed-but-absent case is the control: it proves the guard did not simply
 * swallow every lookup.
 */
it('throws when finalizing an unknown draft', function (string $uuid) {
    expect(fn () => Forms::finalize($uuid))
        ->toThrow(DraftNotFoundException::class);
})->with([
    'malformed id' => 'missing-uuid',
    'well-formed but absent id' => '019f6eef-76b8-73b9-ab3c-78c1a64965a7',
]);

it('resumes a draft by reusing its uuid', function () {
    draftForm();
    $form = Forms::find('apply');

    $first = Forms::draft($form, Request::create('t', parameters: ['apply' => ['g' => ['name' => 'Jane']]]));
    Forms::draft($form, Request::create('t', parameters: [
        'apply' => ['g' => ['name' => 'Jane', 'email' => 'jane@example.com']],
    ]), uuid: $first->uuid);

    $rows = Submission::query()->where('uuid', $first->uuid)->where('status', SubmissionStatus::Draft->value)->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->firstWhere('field_id', Forms::find('apply')->fields->firstWhere('key', 'email')?->id)?->value)
        ->toBe(['value' => 'jane@example.com']);
});

it('refuses to resume a finalized submission as a draft', function () {
    // Regression: resuming only cleared *draft* rows under the uuid, then flipped the
    // aggregate back to Draft and wrote fresh draft rows next to the final ones — a finished
    // submission (already announced via FormSubmitted, possibly under review) reopened, and
    // finalizing it again left two final rows per field under one uuid.
    draftForm();
    $form = Forms::find('apply');
    $values = ['apply' => ['g' => ['name' => 'Jane', 'email' => 'jane@example.com']]];

    $final = Forms::submit($form, Request::create('t', parameters: $values));

    expect(fn () => Forms::draft($form, Request::create('t', parameters: $values), uuid: $final->uuid))
        ->toThrow(DraftNotFoundException::class)
        ->and(FormSubmission::query()->sole()->status)->toBe(SubmissionStatus::Final)
        ->and(Submission::query()->where('uuid', $final->uuid)->count())->toBe(2)
        ->and(Submission::query()->where('uuid', $final->uuid)->draft()->count())->toBe(0);
});

it('refuses to resume another form\'s draft', function () {
    draftForm();
    Forms::define('other', 'Other')
        ->public()
        ->group('g', 'G', function (GroupBuilder $g): void {
            $g->field('note', 'Note');
        })
        ->create();

    $draft = Forms::draft(Forms::find('apply'), Request::create('t', parameters: ['apply' => ['g' => ['name' => 'Jane']]]));

    expect(fn () => Forms::draft(Forms::find('other'), Request::create('t', parameters: ['other' => ['g' => ['note' => 'x']]]), uuid: $draft->uuid))
        ->toThrow(DraftNotFoundException::class)
        ->and(FormSubmission::query()->sole()->form_id)->toBe(Forms::find('apply')->getKey())
        ->and(Submission::query()->where('uuid', $draft->uuid)->draft()->count())->toBe(2);
});

it('refuses to resume a malformed draft uuid', function () {
    draftForm();

    Forms::draft(Forms::find('apply'), Request::create('t', parameters: ['apply' => ['g' => ['name' => 'Jane']]]), uuid: 'not-a-uuid');
})->throws(DraftNotFoundException::class);

/*
 * Review fixes (2026-09-28).
 */

it('refuses to resume a draft for anyone but the sender who saved it', function () {
    draftForm();
    $form = Forms::find('apply');
    $alice = Submitter::query()->create();
    $bob = Submitter::query()->create();

    $draft = Forms::draft($form, Request::create('t', parameters: ['apply' => ['g' => ['name' => 'Alice']]]), $alice);

    expect(fn () => Forms::draft($form, Request::create('t', parameters: ['apply' => ['g' => ['name' => 'Bob']]]), $bob, uuid: $draft->uuid))
        ->toThrow(DraftNotFoundException::class)
        ->and(fn () => Forms::draft($form, Request::create('t', parameters: ['apply' => ['g' => ['name' => 'Anon']]]), uuid: $draft->uuid))
        ->toThrow(DraftNotFoundException::class);

    $aggregate = FormSubmission::query()->sole();

    expect($aggregate->sender_id)->toBe($alice->getKey())
        ->and(Forms::submission($draft->uuid)->get()->value('name'))->toBe('Alice');

    // The owner still resumes it.
    Forms::draft($form, Request::create('t', parameters: ['apply' => ['g' => ['name' => 'Alice B.']]]), $alice, uuid: $draft->uuid);

    expect(Forms::submission($draft->uuid)->get()->value('name'))->toBe('Alice B.');
});

it('does not let a signed-in sender take over an anonymous draft', function () {
    draftForm();
    $form = Forms::find('apply');

    $draft = Forms::draft($form, Request::create('t', parameters: ['apply' => ['g' => ['name' => 'Anon']]]));

    Forms::draft($form, Request::create('t', parameters: ['apply' => ['g' => ['name' => 'Bob']]]), Submitter::query()->create(), uuid: $draft->uuid);
})->throws(DraftNotFoundException::class);

it('lets only one of two racing finalizes win', function () {
    Event::fake([FormSubmitted::class]);

    // `interleave` runs a second finalize of the same draft to completion while the first
    // is between reading the draft and promoting it — the window two processes race in.
    $raced = false;
    Validator::extend('interleave', function () use (&$raced): bool {
        if (! $raced) {
            $raced = true;
            Forms::finalize((string) FormSubmission::query()->value('uuid'));
        }

        return true;
    });

    Forms::define('race', 'Race')
        ->public()
        ->group('g', 'G', fn (GroupBuilder $g) => $g->field('name', 'Name')->rules(['interleave']))
        ->create();

    $draft = Forms::draft(Forms::find('race'), Request::create('t', parameters: ['race' => ['g' => ['name' => 'Jane']]]));

    expect(fn () => Forms::finalize($draft->uuid))->toThrow(DraftNotFoundException::class)
        ->and(Submission::query()->where('uuid', $draft->uuid)->final()->count())->toBe(1);

    Event::assertDispatchedTimes(FormSubmitted::class, 1);
});

it('drops the stored input of a field hidden when the draft is finalized', function () {
    Forms::define('kyc', 'KYC')
        ->public()
        ->group('main', 'Main', function (GroupBuilder $g): void {
            $g->field('adult', 'Adult');
            $g->field('born', 'Born')->date()->visibleWhen('adult', 'yes');
        })
        ->create();

    $draft = Forms::draft(Forms::find('kyc'), Request::create('t', parameters: ['kyc' => ['main' => ['adult' => 'no', 'born' => 'garbage']]]));

    Forms::finalize($draft->uuid);

    expect(Forms::submissions(Forms::find('kyc'))->first()?->values)->toBe(['adult' => 'no', 'born' => null]);
});

describe('drafts with a file field', function () {
    beforeEach(function () {
        Storage::fake('public');
        Storage::fake('local');

        Forms::define('docs', 'Docs')
            ->public()
            ->group('g', 'G', function (GroupBuilder $g): void {
                $g->field('passport', 'Passport')->file()->rules(['required', 'file', 'mimes:pdf', 'max:100']);
            })
            ->create();
    });

    $pdf = fn (string $name = 'a.pdf'): UploadedFile => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n");

    $upload = fn (UploadedFile $file): Request => Request::create('t', 'POST', files: ['docs' => ['g' => ['passport' => $file]]]);

    it('finalizes, validating the stored upload against the field rules', function () use ($pdf, $upload) {
        $draft = Forms::draft(Forms::find('docs'), $upload($pdf()));

        $result = Forms::finalize($draft->uuid);

        $row = Submission::query()->where('uuid', $draft->uuid)->sole();

        expect($result->fieldCount)->toBe(1)
            ->and($row->status)->toBe(SubmissionStatus::Final)
            ->and($row->attachments())->toHaveCount(1);
    });

    it('refuses to finalize when the stored upload breaks the field rules', function () use ($upload) {
        $draft = Forms::draft(Forms::find('docs'), $upload(UploadedFile::fake()->image('p.png', 10, 10)));

        expect(fn () => Forms::finalize($draft->uuid))->toThrow(ValidationException::class, 'must be a file of type: pdf')
            ->and(Submission::query()->where('uuid', $draft->uuid)->sole()->isDraft())->toBeTrue();
    });

    it('refuses to finalize a required upload that was never made', function () {
        $draft = Forms::draft(Forms::find('docs'), Request::create('t', 'POST'));

        expect(fn () => Forms::finalize($draft->uuid))->toThrow(ValidationException::class, 'field is required');
    });

    it('purges the previous upload when the draft is resumed', function () use ($pdf, $upload) {
        $draft = Forms::draft(Forms::find('docs'), $upload($pdf('first.pdf')));
        $first = Submission::query()->sole()->attachments()->sole();

        Forms::draft(Forms::find('docs'), $upload($pdf('second.pdf')), uuid: $draft->uuid);

        expect(Media::query()->count())->toBe(1)
            ->and(Media::query()->where('uuid', $first->uuid)->exists())->toBeFalse()
            ->and(Storage::disk($first->disk)->exists($first->getPath()))->toBeFalse()
            ->and(Submission::query()->sole()->attachments()->sole()->name)->toBe('second');
    });
});
