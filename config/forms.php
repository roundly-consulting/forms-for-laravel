<?php

declare(strict_types=1);
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Resolvers\DefaultResolver;

return [
    'models' => [
        'form' => Form::class,
        'group' => Group::class,
        'field' => Field::class,
        'submission' => Submission::class,
    ],

    'fields' => [
        'default' => DefaultResolver::class,
    ],
];
