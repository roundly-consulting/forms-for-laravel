<?php

declare(strict_types=1);

use Illuminate\Database\UniqueConstraintViolationException;
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

/*
 * Review fixes (2026-09-28) — the key of a soft-deleted form, group or field is free again.
 * The unique indexes counted trashed rows, while every lookup skips them, so re-creating the
 * key threw a UniqueConstraintViolationException.
 */

it('reuses the key of a soft-deleted form for a new form', function () {
    answeredForm()->delete();

    $again = answeredForm();

    expect(Form::query()->where('key', 'survey')->sole()->is($again))->toBeTrue()
        ->and(Form::withTrashed()->where('key', 'survey')->count())->toBe(2);
});

it('syncs a definition whose key a soft-deleted form holds', function () {
    answeredForm()->delete();

    expect(Forms::sync([['key' => 'survey', 'name' => 'Survey again']]))->toBe(['survey'])
        ->and(Form::query()->where('key', 'survey')->sole()->name)->toBe('Survey again');
});

it('re-adds a soft-deleted field and group through update()', function () {
    answeredForm();
    Field::query()->where('key', 'age')->sole()->delete();

    Forms::update('survey')
        ->group('main', 'Main', fn (GroupBuilder $g) => $g->field('age', 'Age again'))
        ->save();

    Group::query()->where('key', 'main')->sole()->delete();

    Forms::update('survey')
        ->group('main', 'Main again', fn (GroupBuilder $g) => $g->field('name', 'Name'))
        ->save();

    expect(Field::query()->where('key', 'age')->sole()->name)->toBe('Age again')
        ->and(Field::withTrashed()->where('key', 'age')->count())->toBe(2)
        ->and(Group::query()->where('key', 'main')->sole()->name)->toBe('Main again');
});

it('releases the key on a query-level delete and takes it back on restore', function () {
    answeredForm();

    Form::query()->where('key', 'survey')->delete();
    answeredForm();

    // Two rows now carry `survey`; only one may be live at a time.
    $trashed = Form::onlyTrashed()->where('key', 'survey')->sole();

    expect(fn () => $trashed->restore())->toThrow(UniqueConstraintViolationException::class);

    Form::query()->where('key', 'survey')->sole()->delete();

    Form::onlyTrashed()->whereKey($trashed->getKey())->restore();

    expect(Form::query()->where('key', 'survey')->sole()->is($trashed))->toBeTrue();
});
