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
