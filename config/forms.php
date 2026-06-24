<?php

declare(strict_types=1);
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Resolvers\DefaultResolver;
use RoundlyConsulting\Forms\Resolvers\FileResolver;

return [
    'models' => [
        'form' => Form::class,
        'group' => Group::class,
        'field' => Field::class,
        'submission' => Submission::class,
    ],

    'fields' => [
        'default' => DefaultResolver::class,
        'file' => FileResolver::class,
    ],

    /*
     * Storage settings used by the FileResolver. `disk` is the filesystem disk
     * uploaded files are stored on (defaults to the app's default disk) and
     * `directory` is the folder within that disk.
     */
    'file' => [
        'disk' => env('FORMS_FILE_DISK', config('filesystems.default', 'local')),
        'directory' => env('FORMS_FILE_DIRECTORY', 'form-uploads'),
    ],

    /*
     * Declaratively defined forms, synced to the database with `forms:sync`.
     * Each entry is a form definition array compatible with
     * FormDefinitionData::fromArray().
     */
    'definitions' => [],
];
