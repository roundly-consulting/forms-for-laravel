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
