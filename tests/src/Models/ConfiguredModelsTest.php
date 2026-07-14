<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\GroupBuilder;
use RoundlyConsulting\Forms\Tests\testable\CustomField;
use RoundlyConsulting\Forms\Tests\testable\CustomForm;
use RoundlyConsulting\Forms\Tests\testable\CustomFormSubmission;
use RoundlyConsulting\Forms\Tests\testable\CustomGroup;
use RoundlyConsulting\Forms\Tests\testable\CustomSubmission;
use RoundlyConsulting\Forms\Tests\testable\Submitter;

/**
 * `forms.models.*` invites a host to swap in its own subclass. Eloquent derives a
 * `hasMany` foreign key from the PARENT'S CLASS NAME, so a relation declared without
 * an explicit key silently looks for `custom_form_id` the moment a host does exactly
 * what the config documents — and every insert and read breaks.
 *
 * These tests run the whole lifecycle through host subclasses and pin each foreign
 * key by name. Removing an explicit key turns them red.
 */
beforeEach(function (): void {
    config()->set('forms.models.form', CustomForm::class);
    config()->set('forms.models.group', CustomGroup::class);
    config()->set('forms.models.field', CustomField::class);
    config()->set('forms.models.submission', CustomSubmission::class);
    config()->set('forms.models.form_submission', CustomFormSubmission::class);
});

function swappedForm(): void
{
    Forms::define('signup', 'Signup')
        ->public()
        ->group('about', 'About', function (GroupBuilder $group): void {
            $group->field('email', 'Email');
            $group->field('age', 'Age')->type('number');
        })
        ->create();
}

it('keys every relation off the packaged foreign key, not the host class name', function (): void {
    swappedForm();

    $form = Forms::find('signup');
    $group = $form->groups->first();
    $field = $group?->fields->first();

    expect($form)->toBeInstanceOf(CustomForm::class)
        ->and($group)->toBeInstanceOf(CustomGroup::class)
        ->and($field)->toBeInstanceOf(CustomField::class);

    expect($form->groups()->getForeignKeyName())->toBe('form_id')
        ->and($form->submissions()->getForeignKeyName())->toBe('form_id')
        ->and($group?->fields()->getForeignKeyName())->toBe('group_id')
        ->and($field?->submissions()->getForeignKeyName())->toBe('field_id')
        ->and((new CustomFormSubmission)->submissions()->getForeignKeyName())->toBe('form_submission_id');
});

it('submits through the configured models end to end', function (): void {
    swappedForm();
    $sender = Submitter::query()->create();

    $result = Forms::submit(Forms::find('signup'), Request::create('/t', parameters: [
        'signup' => ['about' => ['email' => 'a@b.com', 'age' => '30']],
    ]), $sender);

    $rows = CustomSubmission::query()->where('uuid', $result->uuid)->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->first())->toBeInstanceOf(CustomSubmission::class);

    // The reads that the derived-FK bug broke: parent -> children, child -> parent.
    $aggregate = CustomFormSubmission::query()->where('uuid', $result->uuid)->sole();

    expect($aggregate->submissions()->count())->toBe(2)
        ->and($aggregate->form)->toBeInstanceOf(CustomForm::class);

    $form = Forms::find('signup');

    expect($form->submissions()->count())->toBe(2)
        ->and($form->groups()->count())->toBe(1)
        ->and($sender->formSubmissions()->count())->toBe(2);

    $field = CustomField::query()->where('key', 'email')->sole();

    expect($field->submissions()->count())->toBe(1)
        ->and($field->group)->toBeInstanceOf(CustomGroup::class)
        ->and($field->form)->toBeInstanceOf(CustomForm::class);
});

it('drafts and finalizes through the configured models', function (): void {
    swappedForm();
    $sender = Submitter::query()->create();

    $draft = Forms::draft(Forms::find('signup'), Request::create('/t', parameters: [
        'signup' => ['about' => ['email' => 'a@b.com', 'age' => '30']],
    ]), $sender);

    expect(CustomSubmission::query()->draft()->where('uuid', $draft->uuid)->count())->toBe(2);

    $final = Forms::finalize($draft->uuid);

    expect(CustomSubmission::query()->final()->where('uuid', $final->uuid)->count())->toBe(2);
});

it('assembles submissions through the configured models', function (): void {
    swappedForm();

    Forms::submit(Forms::find('signup'), Request::create('/t', parameters: [
        'signup' => ['about' => ['email' => 'a@b.com', 'age' => '30']],
    ]));

    $assembled = Forms::submissions(Forms::find('signup'))->first();

    expect($assembled?->values)->toEqualCanonicalizing(['email' => 'a@b.com', 'age' => 30]);
});
