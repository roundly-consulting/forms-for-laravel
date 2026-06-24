<?php

declare(strict_types=1);

use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\GroupBuilder;
use RoundlyConsulting\Forms\Models\Field;

it('provides typed field-builder shortcuts', function () {
    Forms::define('signup', 'Sign up')
        ->group('g', 'G', function (GroupBuilder $g): void {
            $g->field('email', 'Email')->email()->required();
            $g->field('bio', 'Bio')->textarea();
            $g->field('terms', 'Terms')->checkbox();
            $g->field('age', 'Age')->number();
            $g->field('dob', 'DOB')->date();
            $g->field('role', 'Role')->select(['admin' => 'Admin', 'user' => 'User']);
            $g->field('avatar', 'Avatar')->file();
        })
        ->create();

    $email = Field::query()->where('key', 'email')->sole();
    expect($email->type)->toBe('email')
        ->and($email->validations)->toBe(['email', 'required']);

    expect(Field::query()->where('key', 'bio')->sole()->type)->toBe('textarea')
        ->and(Field::query()->where('key', 'terms')->sole()->validations)->toBe(['boolean'])
        ->and(Field::query()->where('key', 'age')->sole()->validations)->toBe(['numeric'])
        ->and(Field::query()->where('key', 'dob')->sole()->validations)->toBe(['date'])
        ->and(Field::query()->where('key', 'avatar')->sole()->type)->toBe('file');

    $role = Field::query()->where('key', 'role')->sole();
    expect($role->type)->toBe('select')
        ->and($role->options)->toBe(['admin' => 'Admin', 'user' => 'User']);
});

it('accepts ignored key/name args on email for fluency', function () {
    Forms::define('f', 'F')
        ->group('g', 'G', function (GroupBuilder $g): void {
            $g->field('email', 'Email')->email('email', 'Email');
        })
        ->create();

    expect(Field::query()->where('key', 'email')->sole()->type)->toBe('email');
});

it('sets an autofill class on a builder field', function () {
    Forms::define('f', 'F')
        ->group('g', 'G', function (GroupBuilder $g): void {
            $g->field('country', 'Country')->autofill('App\\Autofill\\Country');
        })
        ->create();

    expect(Field::query()->where('key', 'country')->sole()->autofill)->toBe('App\\Autofill\\Country');
});

it('does not duplicate a rule added twice', function () {
    Forms::define('f', 'F')
        ->group('g', 'G', function (GroupBuilder $g): void {
            $g->field('x', 'X')->required()->required();
        })
        ->create();

    expect(Field::query()->where('key', 'x')->sole()->validations)->toBe(['required']);
});
