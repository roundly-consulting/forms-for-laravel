<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Forms\Events\FieldCreated;
use RoundlyConsulting\Forms\Events\FieldUpdated;
use RoundlyConsulting\Forms\Events\GroupCreated;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\GroupBuilder;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;

function baseForm(): Form
{
    return Forms::define('contact', 'Contact')
        ->group('details', 'Details', function (GroupBuilder $g): void {
            $g->field('name', 'Name')->rules(['required']);
        })
        ->create();
}

it('renames groups and fields and updates form attributes', function () {
    Carbon::setTestNow('2030-01-01 12:00:00');
    baseForm();

    Forms::update('contact')
        ->name('Contact us')
        ->public()
        ->group('details', 'Your details', function (GroupBuilder $g): void {
            $g->field('name', 'Full name')->rules(['required']);
        })
        ->save();

    $form = Form::query()->forKey('contact')->sole();
    expect($form->name)->toBe('Contact us')
        ->and($form->is_public)->toBeTrue()
        ->and(Group::query()->where('key', 'details')->sole()->name)->toBe('Your details')
        ->and(Field::query()->where('key', 'name')->sole()->name)->toBe('Full name')
        ->and(Field::query()->count())->toBe(1);

    Carbon::setTestNow();
});

it('updates the expiry via the builder', function () {
    $expiresAt = Carbon::parse('2031-06-01 00:00:00');
    baseForm();

    Forms::update('contact')->expiresAt($expiresAt)->save();

    expect(Form::query()->forKey('contact')->sole()->expires_at?->equalTo($expiresAt))->toBeTrue();
});

it('adds new groups and fields while leaving others intact', function () {
    baseForm();

    Forms::update('contact')
        ->group('details', 'Details', function (GroupBuilder $g): void {
            $g->field('email', 'Email')->rules(['email']);
        })
        ->group('message', 'Message', function (GroupBuilder $g): void {
            $g->field('body', 'Body');
        })
        ->save();

    expect(Group::query()->count())->toBe(2)
        ->and(Field::query()->pluck('key')->sort()->values()->all())->toBe(['body', 'email', 'name']);
});

it('fires create and update events only for changed records', function () {
    baseForm();
    Event::fake([FieldCreated::class, FieldUpdated::class, GroupCreated::class]);

    Forms::update('contact')
        ->group('details', 'Details', function (GroupBuilder $g): void {
            $g->field('name', 'Renamed')->rules(['required']); // changed -> updated
            $g->field('phone', 'Phone'); // new -> created
        })
        ->group('extra', 'Extra', function (GroupBuilder $g): void {
            $g->field('x', 'X');
        })
        ->save();

    Event::assertDispatched(FieldUpdated::class, 1);
    Event::assertDispatched(FieldCreated::class, 2);
    Event::assertDispatched(GroupCreated::class, 1);
});

it('does not touch records when nothing changed', function () {
    baseForm();
    Event::fake([FieldUpdated::class]);

    Forms::update('contact')
        ->group('details', 'Details', function (GroupBuilder $g): void {
            $g->field('name', 'Name')->rules(['required']);
        })
        ->save();

    Event::assertNotDispatched(FieldUpdated::class);
});
