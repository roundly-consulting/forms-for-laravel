<?php

declare(strict_types=1);

/**
 * The config contract, pinned in both directions:
 *
 *  - forward — every key the code reads is shipped. This is shops #18, whose entire
 *    store-credit feature read `shops.payments.*` while the file shipped `payment.*`; 330
 *    tests stayed green because the suite set the same wrong key.
 *  - reverse — every shipped leaf is read. A documented key nothing reads is dead config
 *    that lies to the host: media #27's `max_file_size` cap that never applied. Forms ships
 *    its own `media.max_file_size`, `media.accepted_mime_types` and `media.responsive_widths`
 *    — the exact shape of media #27, one package downstream.
 */
it('ships exactly the config keys it reads', function (): void {
    expect(__DIR__.'/../../config/forms.php')->toSatisfyConfigContract(__DIR__.'/../../src', [
        // `forms.models.*` are read through the toolkit's ModelResolver seam
        // (FormModel/GroupModel/FieldModel/SubmissionModel/FormSubmissionModel) rather than a
        // literal `config()` call. They are real reads — they drive the whole model swap —
        // but they are not `config(` tokens, so the prefix is what makes them visible to the
        // scraper.
        'extraReadPrefixes' => ['forms.'],

        // `Field::resolver()` reads the resolver map wholesale and then falls back to a
        // LITERAL offset: `$resolvers[$this->type] ?? $resolvers['default']`. Declaring the
        // variable proves `forms.fields.default` is genuinely read by name rather than
        // allow-listed away — the fallback every unmapped field type depends on.
        'sectionVariables' => [
            'Field.php' => [
                '$resolvers' => 'forms.fields',
            ],
        ],

        // The leaves below are DATA, not named switches, and the distinction is what makes
        // this an allow-list rather than a bug.
        //
        // `forms.field_types` and `forms.fields` are maps keyed by a field's `type`, read
        // wholesale and looked up at runtime (`$map[$this->type]`). No code names
        // `forms.field_types.number` — it cannot, the key is a value. So the reverse
        // direction's per-leaf proof (an exact read, or a literal offset) is structurally
        // unavailable here, and that is a property of the config's shape, not a dead key.
        //
        // Listing every leaf rather than silencing the subtree is deliberate: `allowUnread`
        // is exact-match and `assertAllowUnreadIsLive` fails on a stale entry, so adding a
        // field-type mapping forces a deliberate line here instead of quietly inheriting an
        // exemption. That is the cost of keeping the check honest for the other 40-odd keys.
        'allowUnread' => [
            'forms.fields.file',
            'forms.fields.image',
            'forms.field_types.number',
            'forms.field_types.range',
            'forms.field_types.float',
            'forms.field_types.decimal',
            'forms.field_types.checkbox',
            'forms.field_types.boolean',
            'forms.field_types.toggle',
            'forms.field_types.date',
            'forms.field_types.datetime',
            'forms.field_types.time',
            'forms.field_types.multiselect',
            'forms.field_types.checkboxes',
            'forms.field_types.tags',
        ],

        // Deliberately NO `excludeFromReverse` for the provider. FormsServiceProvider's
        // contributesToAbout() closure calls config('forms.…') for real, through
        // listSize()/presence()/bytes()/attachments() — it is the genuine (and for several
        // keys the only) reader. Excluding it would discard readers and weaken the reverse
        // direction for nothing.
    ]);
});
