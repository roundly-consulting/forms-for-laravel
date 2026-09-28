<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Forms\DataTransferObjects\AssembledSubmission;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\GroupBuilder;
use RoundlyConsulting\Forms\Tests\testable\Submitter;

function readableForm(): void
{
    Forms::define('survey', 'Survey')
        ->public()
        ->group('g', 'G', function (GroupBuilder $g): void {
            $g->field('name', 'Name');
            $g->field('color', 'Color');
        })
        ->create();
}

it('assembles raw rows into keyed value sets per uuid', function () {
    readableForm();
    $form = Forms::find('survey');

    Forms::submit($form, Request::create('t', parameters: ['survey' => ['g' => ['name' => 'Jane', 'color' => 'blue']]]));

    $sets = Forms::submissions($form)->get();

    expect($sets)->toHaveCount(1)
        ->and($sets->first())->toBeInstanceOf(AssembledSubmission::class)
        ->and($sets->first()?->values)->toEqualCanonicalizing(['name' => 'Jane', 'color' => 'blue'])
        ->and($sets->first()?->value('name'))->toBe('Jane')
        ->and($sets->first()?->value('missing', 'x'))->toBe('x');
});

it('filters by sender', function () {
    readableForm();
    $form = Forms::find('survey');
    $jane = Submitter::query()->create();
    $bob = Submitter::query()->create();

    Forms::submit($form, Request::create('t', parameters: ['survey' => ['g' => ['name' => 'Jane']]]), $jane);
    Forms::submit($form, Request::create('t', parameters: ['survey' => ['g' => ['name' => 'Bob']]]), $bob);

    $sets = Forms::submissions($form)->forSender($jane)->get();

    expect($sets)->toHaveCount(1)
        ->and($sets->first()?->value('name'))->toBe('Jane');
});

it('orders latest first by default and oldest on request', function () {
    readableForm();
    $form = Forms::find('survey');

    Carbon::setTestNow('2030-01-01 10:00:00');
    Forms::submit($form, Request::create('t', parameters: ['survey' => ['g' => ['name' => 'first']]]));
    Carbon::setTestNow('2030-01-01 11:00:00');
    Forms::submit($form, Request::create('t', parameters: ['survey' => ['g' => ['name' => 'second']]]));
    Carbon::setTestNow();

    expect(Forms::submissions($form)->latest()->first()?->value('name'))->toBe('second')
        ->and(Forms::submissions($form)->oldest()->first()?->value('name'))->toBe('first')
        ->and(Forms::submissions($form)->count())->toBe(2);
});

it('excludes drafts unless asked to include them', function () {
    readableForm();
    $form = Forms::find('survey');

    Forms::draft($form, Request::create('t', parameters: ['survey' => ['g' => ['name' => 'draft']]]));

    expect(Forms::submissions($form)->count())->toBe(0)
        ->and(Forms::submissions($form)->withDrafts()->count())->toBe(1);
});

/*
 * Review fixes (2026-09-28) — a field key may repeat across groups (the unique index is per
 * group). Keying the assembled values by the bare field key let the later group's answer
 * overwrite the earlier one's.
 */

it('keeps both answers when two groups share a field key', function () {
    $form = Forms::define('family', 'Family')
        ->public()
        ->group('applicant', 'Applicant', function (GroupBuilder $g): void {
            $g->field('name', 'Name');
            $g->field('age', 'Age');
        })
        ->group('guardian', 'Guardian', function (GroupBuilder $g): void {
            $g->field('name', 'Name');
        })
        ->create();

    Forms::submit($form, Request::create('t', parameters: ['family' => [
        'applicant' => ['name' => 'Kid', 'age' => '9'],
        'guardian' => ['name' => 'Parent'],
    ]]));

    $set = Forms::submissions($form)->first();

    expect($set?->values)->toBe(['applicant.name' => 'Kid', 'age' => '9', 'guardian.name' => 'Parent'])
        ->and($set?->value('guardian.name'))->toBe('Parent');
});

it('lists values in form order: groups by order, then fields by order', function () {
    $form = Forms::define('ordered', 'Ordered')
        ->public()
        ->group('second', 'Second', function (GroupBuilder $g): void {
            $g->order(2);
            $g->field('b', 'B')->order(2);
            $g->field('a', 'A')->order(1);
        })
        ->group('first', 'First', function (GroupBuilder $g): void {
            $g->order(1);
            $g->field('z', 'Z');
        })
        ->create();

    Forms::submit($form, Request::create('t', parameters: ['ordered' => [
        'second' => ['a' => '1', 'b' => '2'],
        'first' => ['z' => '0'],
    ]]));

    expect(array_keys(Forms::submissions($form)->first()?->values ?? []))->toBe(['z', 'a', 'b']);
});
