<?php

declare(strict_types=1);
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\FormSubmission;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Resolvers\DefaultResolver;
use RoundlyConsulting\Forms\Resolvers\MediaFileResolver;

return [
    'models' => [
        'form' => Form::class,
        'group' => Group::class,
        'field' => Field::class,
        'submission' => Submission::class,
        'form_submission' => FormSubmission::class,
    ],

    'fields' => [
        'default' => DefaultResolver::class,
        'file' => MediaFileResolver::class,
        'image' => MediaFileResolver::class,
    ],

    /*
     * Maps a field's `type` to the attributes-for-laravel AttributeType used to
     * cast its stored value back to a real PHP type on read and to type-check
     * submitted values. Types not listed here fall back to a plain string,
     * preserving the historical raw-value behaviour. Values must be one of the
     * AttributeType cases: string, integer, float, boolean, array, datetime.
     */
    'field_types' => [
        'number' => 'integer',
        'range' => 'integer',
        'float' => 'float',
        'decimal' => 'float',
        'checkbox' => 'boolean',
        'boolean' => 'boolean',
        'toggle' => 'boolean',
        'date' => 'datetime',
        'datetime' => 'datetime',
        'time' => 'datetime',
        'multiselect' => 'array',
        'checkboxes' => 'array',
        'tags' => 'array',
    ],

    /*
     * Media settings for file/image fields, backed by
     * roundly-consulting/media-library-for-laravel. Uploaded files attach to the
     * per-field submission row's own bucket (image variants for images, other
     * files pass through) and read back through signed/temporary URLs.
     */
    'media' => [
        // Bucket name the submission row registers on the media-library model.
        'bucket' => 'attachment',

        // Visibility of stored uploads: 'private' (default, signed streaming) or 'public'.
        'visibility' => 'private',

        // Disk for the submission media. null => the media-library default disk.
        'disk' => env('FORMS_MEDIA_DISK'),

        // Restrict accepted mime types. null/[] => the media-library default (open).
        'accepted_mime_types' => null,

        // Max upload size in bytes. null => the media-library default.
        'max_file_size' => null,

        // Responsive image widths. null => the media-library default ladder.
        'responsive_widths' => null,

        // Lifetime (minutes) of a signed attachment URL. null => the media default.
        'temporary_url_lifetime' => null,
    ],

    /*
     * Submission review via roundly-consulting/approvals-for-laravel. When
     * enabled, a whole submission (the FormSubmission aggregate) can be routed
     * through the approvals engine with `Forms::review($submission)`. Disabled by
     * default, preserving the plain submit/finalize lifecycle.
     */
    'approvals' => [
        'enabled' => env('FORMS_APPROVALS_ENABLED', false),
    ],

    /*
     * Declaratively defined forms, synced to the database with `forms:sync`.
     * Each entry is a form definition array compatible with
     * FormDefinitionData::fromArray().
     */
    'definitions' => [],
];
