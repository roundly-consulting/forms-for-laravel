<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use RoundlyConsulting\Forms\DataTransferObjects\FieldDefinitionData;
use RoundlyConsulting\Forms\DataTransferObjects\FormDefinitionData;
use RoundlyConsulting\Forms\DataTransferObjects\GroupDefinitionData;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionResult;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Submission;

function seedForm(): Form
{
    return Forms::create(new FormDefinitionData(
        key: 'contact',
        name: 'Contact us',
        isPublic: true,
        groups: [
            new GroupDefinitionData('details', 'Your details', fields: [
                new FieldDefinitionData('name', 'Name', validations: ['required']),
            ]),
        ],
    ));
}

it('finds a form through the facade', function () {
    seedForm();

    $form = Forms::find('contact');

    expect($form)->toBeInstanceOf(Form::class)
        ->and($form->key)->toBe('contact');
});

it('validates through the facade', function () {
    seedForm();

    $form = Forms::find('contact');

    $validated = Forms::validate($form, Request::create('t', parameters: [
        'contact' => ['details' => ['name' => 'Jane']],
    ]));

    expect($validated)->toBe(['contact' => ['details' => ['name' => 'Jane']]]);
});

it('submits through the facade and returns a result', function () {
    seedForm();

    $form = Forms::find('contact');

    $result = Forms::submit($form, Request::create('t', parameters: [
        'contact' => ['details' => ['name' => 'Jane']],
    ]));

    expect($result)->toBeInstanceOf(SubmissionResult::class)
        ->and($result->fieldCount)->toBe(1)
        ->and(Submission::query()->where('uuid', $result->uuid)->count())->toBe(1);
});

it('keeps the legacy storeSubmission string return', function () {
    seedForm();

    $form = Forms::find('contact');

    $uuid = Forms::storeSubmission($form, Request::create('t', parameters: [
        'contact' => ['details' => ['name' => 'Jane']],
    ]));

    expect($uuid)->toBeString()
        ->and(Submission::query()->where('uuid', $uuid)->exists())->toBeTrue();
});
