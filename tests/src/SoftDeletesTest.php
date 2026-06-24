<?php

declare(strict_types=1);

use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Models\Submission;

it('soft deletes each model', function () {
    $form = Form::factory()->create();
    $group = Group::factory()->for($form)->create();
    $field = Field::factory()->for($form)->for($group)->create();
    $submission = Submission::factory()->for($form)->for($group)->for($field)->create();

    foreach ([$form, $group, $field, $submission] as $model) {
        $model->delete();
        expect($model->trashed())->toBeTrue();
    }
});

it('excludes soft-deleted forms from default queries but keeps them with trashed', function () {
    $form = Form::factory()->create(['key' => 'gone']);
    $form->delete();

    expect(Form::query()->where('key', 'gone')->exists())->toBeFalse()
        ->and(Form::withTrashed()->where('key', 'gone')->exists())->toBeTrue();
});

it('restores a soft-deleted form', function () {
    $form = Form::factory()->create(['key' => 'back']);
    $form->delete();
    $form->restore();

    expect(Form::query()->where('key', 'back')->exists())->toBeTrue();
});
