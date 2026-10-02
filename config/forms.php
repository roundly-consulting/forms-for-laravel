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

    /*
    |--------------------------------------------------------------------------
    | Key Type
    |--------------------------------------------------------------------------
    |
    | The key type used for the polymorphic sender columns on submissions and
    | form_submissions. Use "uuid" or "ulid" when the models those columns point
    | at use UUID/ULID primary keys, otherwise leave it as "bigint". Your morph
    | targets must share one key type; set this to match. Any unrecognized value
    | falls back to "bigint".
    |
    | Supported: "bigint", "uuid", "ulid"
    |
    */

    'key_type' => env('FORMS_KEY_TYPE', 'bigint'),

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
     *
     * `number` and `range` are whole numbers; use a `decimal` or `float` field for
     * fractions. A `time` field is left unmapped on purpose: a time of day is not a
     * moment, so it reads back exactly as stored (validate it with e.g. `date_format:H:i`).
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

        // Disk for the submission media, whatever its visibility. null => chosen by visibility:
        // private uploads go to 'private_disk' below, public ones to the media-library default
        // disk ('public' out of the box).
        'disk' => env('FORMS_MEDIA_DISK'),

        // Disk for PRIVATE uploads (originals and variants) when 'disk' is null. It must not be
        // web-served — the media-library default 'public' disk is (under /storage once
        // `storage:link` runs), which would make a private upload reachable without a signature.
        // Laravel's 'local' disk (storage/app/private) is not; a private S3 disk works too.
        'private_disk' => env('FORMS_MEDIA_PRIVATE_DISK', 'local'),

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
