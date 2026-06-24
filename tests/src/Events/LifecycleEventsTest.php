<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Forms\Events\FieldCreated;
use RoundlyConsulting\Forms\Events\FieldDeleted;
use RoundlyConsulting\Forms\Events\FieldUpdated;
use RoundlyConsulting\Forms\Events\FormCreated;
use RoundlyConsulting\Forms\Events\FormDeleted;
use RoundlyConsulting\Forms\Events\FormUpdated;
use RoundlyConsulting\Forms\Events\GroupCreated;
use RoundlyConsulting\Forms\Events\GroupDeleted;
use RoundlyConsulting\Forms\Events\GroupUpdated;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;

it('dispatches form lifecycle events', function () {
    Event::fake();

    $form = Form::factory()->create();
    Event::assertDispatched(FormCreated::class);

    $form->update(['name' => 'Renamed']);
    Event::assertDispatched(FormUpdated::class);

    $form->delete();
    Event::assertDispatched(FormDeleted::class);
});

it('dispatches group lifecycle events', function () {
    $form = Form::factory()->create();

    Event::fake();

    $group = Group::factory()->for($form)->create();
    Event::assertDispatched(GroupCreated::class);

    $group->update(['name' => 'Renamed']);
    Event::assertDispatched(GroupUpdated::class);

    $group->delete();
    Event::assertDispatched(GroupDeleted::class);
});

it('dispatches field lifecycle events', function () {
    $form = Form::factory()->create();
    $group = Group::factory()->for($form)->create();

    Event::fake();

    $field = Field::factory()->for($form)->for($group)->create();
    Event::assertDispatched(FieldCreated::class);

    $field->update(['name' => 'Renamed']);
    Event::assertDispatched(FieldUpdated::class);

    $field->delete();
    Event::assertDispatched(FieldDeleted::class);
});
