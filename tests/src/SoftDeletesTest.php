<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\GroupBuilder;
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

/*
 * Review fixes (2026-09-28) — soft-deleting a field (or its group, or the form) must not
 * break reading the answers already stored against it.
 */

function answeredForm(): Form
{
    return Forms::define('survey', 'Survey')
        ->public()
        ->group('main', 'Main', function (GroupBuilder $g): void {
            $g->field('name', 'Name');
            $g->field('age', 'Age')->number();
        })
        ->create();
}

it('keeps reading past submissions after one of their fields is soft-deleted', function () {
    $form = answeredForm();
    $result = Forms::submit($form, Request::create('t', 'POST', ['survey' => ['main' => ['name' => 'Ann', 'age' => '42']]]));

    Field::query()->where('key', 'age')->sole()->delete();

    $row = Submission::query()->whereHas('field', fn ($q) => $q->where('key', 'age'))->sole();

    expect(Forms::submissions($form)->first()?->values)->toBe(['name' => 'Ann', 'age' => 42])
        ->and(Forms::submission($result->uuid)->get()->value('age'))->toBe(42)
        ->and($row->path())->toBe('survey.main.age')
        ->and($row->typedValue())->toBe(42);
});

it('keeps reading past submissions after their group and form are soft-deleted', function () {
    $form = answeredForm();
    $result = Forms::submit($form, Request::create('t', 'POST', ['survey' => ['main' => ['name' => 'Ann', 'age' => '42']]]));

    Group::query()->where('key', 'main')->sole()->delete();
    $form->delete();

    $row = Submission::query()->where('uuid', $result->uuid)->firstOrFail();

    expect($row->path())->toStartWith('survey.main.')
        ->and(Forms::submission($result->uuid)->get()->values)->toBe(['name' => 'Ann', 'age' => 42]);
});

it('finalizes a draft whose field was soft-deleted after it was saved', function () {
    $form = answeredForm();
    $draft = Forms::draft($form, Request::create('t', 'POST', ['survey' => ['main' => ['name' => 'Ann', 'age' => '42']]]));

    Field::query()->where('key', 'name')->sole()->delete();

    $result = Forms::finalize($draft->uuid);

    expect($result->fieldCount)->toBe(2)
        ->and(Forms::submissions($form)->first()?->values)->toBe(['name' => 'Ann', 'age' => 42]);
});
