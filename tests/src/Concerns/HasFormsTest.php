<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\GroupBuilder;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Tests\testable\Submitter;

function traitForm(): void
{
    Forms::define('newsletter', 'Newsletter')
        ->public()
        ->group('g', 'G', function (GroupBuilder $g): void {
            $g->field('email', 'Email');
        })
        ->create();
}

it('submits to a form as the sender via submitTo', function () {
    traitForm();
    $user = Submitter::query()->create();

    $result = $user->submitTo(Forms::find('newsletter'), Request::create('t', parameters: [
        'newsletter' => ['g' => ['email' => 'a@b.com']],
    ]));

    expect(Submission::query()->where('uuid', $result->uuid)->count())->toBe(1)
        ->and($user->formSubmissions()->count())->toBe(1)
        ->and($user->formSubmissions->first()?->sender->is($user))->toBeTrue();
});

it('drafts to a form as the sender via draftTo', function () {
    traitForm();
    $user = Submitter::query()->create();

    $result = $user->draftTo(Forms::find('newsletter'), Request::create('t', parameters: [
        'newsletter' => ['g' => ['email' => 'a@b.com']],
    ]));

    expect(Submission::query()->where('uuid', $result->uuid)->draft()->count())->toBe(1)
        ->and($user->formSubmissions()->draft()->count())->toBe(1);
});
