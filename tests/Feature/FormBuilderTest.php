<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\GroupBuilder;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;

it('defines and persists a whole form fluently', function () {
    $expiresAt = Carbon::parse('2030-01-01 00:00:00');

    $form = Forms::define('contact', 'Contact us')
        ->public()
        ->expiresAt($expiresAt)
        ->group('details', 'Your details', function (GroupBuilder $g): void {
            $g->field('name', 'Name')->rules(['required'])->help('Full name');
            $g->field('email', 'Email')->type('email')->rules(['required', 'email']);
        })
        ->group('message', 'Message', function (GroupBuilder $g): void {
            $g->field('body', 'Message')->type('textarea')->rules(['required']);
        })
        ->create();

    expect($form)->toBeInstanceOf(Form::class)
        ->and($form->is_public)->toBeTrue()
        ->and($form->expires_at->equalTo($expiresAt))->toBeTrue()
        ->and(Group::query()->where('form_id', $form->getKey())->count())->toBe(2)
        ->and(Field::query()->where('form_id', $form->getKey())->count())->toBe(3);

    $email = Field::query()->where('key', 'email')->sole();
    expect($email->type)->toBe('email')
        ->and($email->validations)->toBe(['required', 'email'])
        ->and($email->order)->toBe(1);

    $details = Group::query()->where('key', 'details')->sole();
    expect($details->order)->toBe(0);
});

it('auto-assigns order by declaration sequence and respects explicit order', function () {
    $form = Forms::define('survey', 'Survey')
        ->group('first', 'First', function (GroupBuilder $g): void {
            $g->field('a', 'A');
            $g->field('b', 'B')->order(5);
        })
        ->create();

    expect(Field::query()->where('key', 'a')->sole()->order)->toBe(0)
        ->and(Field::query()->where('key', 'b')->sole()->order)->toBe(5);

    unset($form);
});

it('supports options and help on builder fields and explicit group order', function () {
    Forms::define('survey', 'Survey')
        ->group('first', 'First', function (GroupBuilder $g): void {
            $g->order(7);
            $g->field('country', 'Country')->options(['CZ' => 'Czechia'])->help('Pick one');
        })
        ->create();

    $field = Field::query()->where('key', 'country')->sole();

    expect($field->options)->toBe(['CZ' => 'Czechia'])
        ->and($field->help)->toBe('Pick one')
        ->and(Group::query()->where('key', 'first')->sole()->order)->toBe(7);
});

/*
 * Review fixes (2026-09-28) — an explicit `order(0)` is an order like any other; only an
 * order that was never given falls back to the declaration index.
 */

it('keeps an explicit order of 0 on create, update and sync', function () {
    Forms::define('ranked', 'Ranked')
        ->group('later', 'Later', fn (GroupBuilder $g) => $g->order(3)->field('a', 'A')->order(5))
        ->group('first', 'First', function (GroupBuilder $g): void {
            $g->order(0);
            $g->field('x', 'X');
            $g->field('b', 'B')->order(0);
        })
        ->create();

    $order = fn (string $model, string $key): int => $model::query()->where('key', $key)->sole()->order;

    expect($order(Group::class, 'later'))->toBe(3)
        ->and($order(Group::class, 'first'))->toBe(0)
        ->and($order(Field::class, 'a'))->toBe(5)
        ->and($order(Field::class, 'b'))->toBe(0);

    Forms::update('ranked')
        ->group('later', 'Later', fn (GroupBuilder $g) => $g->field('a', 'A')->order(0))
        ->save();

    expect($order(Field::class, 'a'))->toBe(0)
        ->and($order(Group::class, 'later'))->toBe(0);

    Forms::sync([[
        'key' => 'ranked',
        'name' => 'Ranked',
        'groups' => [
            ['key' => 'later', 'name' => 'Later', 'order' => 4, 'fields' => [['key' => 'a', 'name' => 'A', 'order' => 2]]],
            ['key' => 'first', 'name' => 'First', 'order' => 0, 'fields' => [['key' => 'x', 'name' => 'X'], ['key' => 'b', 'name' => 'B', 'order' => 0]]],
        ],
    ]]);

    expect($order(Group::class, 'later'))->toBe(4)
        ->and($order(Group::class, 'first'))->toBe(0)
        ->and($order(Field::class, 'a'))->toBe(2)
        ->and($order(Field::class, 'x'))->toBe(0)
        ->and($order(Field::class, 'b'))->toBe(0);
});
