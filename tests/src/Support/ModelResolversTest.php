<?php

declare(strict_types=1);

use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\FormSubmission;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Support\FieldModel;
use RoundlyConsulting\Forms\Support\FormModel;
use RoundlyConsulting\Forms\Support\FormSubmissionModel;
use RoundlyConsulting\Forms\Support\GroupModel;
use RoundlyConsulting\Forms\Support\SubmissionModel;
use RoundlyConsulting\Forms\Tests\testable\CustomForm;
use RoundlyConsulting\Forms\Tests\testable\Submitter;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

it('resolves the packaged model by default', function (): void {
    expect(FormModel::class())->toBe(Form::class)
        ->and(GroupModel::class())->toBe(Group::class)
        ->and(FieldModel::class())->toBe(Field::class)
        ->and(SubmissionModel::class())->toBe(Submission::class)
        ->and(FormSubmissionModel::class())->toBe(FormSubmission::class);
});

it('resolves a host subclass configured in forms.models', function (): void {
    config()->set('forms.models.form', CustomForm::class);

    expect(FormModel::class())->toBe(CustomForm::class)
        ->and(FormModel::new())->toBeInstanceOf(CustomForm::class);
});

it('falls back to the packaged model when the configured class is a model but not ours', function (): void {
    // A real Eloquent model that cannot answer the package's scopes and relations.
    config()->set('forms.models.form', Submitter::class);

    expect(FormModel::class())->toBe(Form::class);
});

it('throws when the configured class is not an eloquent model', function (): void {
    config()->set('forms.models.submission', 'NotAModelAtAll');

    expect(fn (): string => SubmissionModel::class())
        ->toThrow(InvalidConfigurationException::class);
});

it('builds a fresh instance of every configured model', function (): void {
    expect(GroupModel::new())->toBeInstanceOf(Group::class)
        ->and(FieldModel::new())->toBeInstanceOf(Field::class)
        ->and(SubmissionModel::new())->toBeInstanceOf(Submission::class)
        ->and(FormSubmissionModel::new())->toBeInstanceOf(FormSubmission::class);
});
