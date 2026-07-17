<?php

declare(strict_types=1);

/**
 * The secret-safe `about` capture (A).
 *
 * Purchases #13 is the bug this exists for: the fleet's most credential-heavy `about`
 * section was guarded by negative assertions against `app(Kernel::class)->output()`, which
 * returns `''`. Every "does not leak" check was vacuous — passing against empty output.
 *
 * Forms' leak surface is host vocabulary, not credentials: a form key, a field type and a
 * mime type are all things a host named, and the attachment disk is host topology. The
 * provider reports each by size or presence.
 *
 * `mustRender` is required and non-empty, so the negative half can never pass over empty
 * output. Its entries deliberately do not overlap the secret surface: a `mustRender` string
 * that also appears in a secret makes a bite proof fire on the wrong half.
 */
it('renders the forms section without leaking host vocabulary', function (): void {
    config()->set('forms.media.disk', 's3-tenant-uploads');
    config()->set('forms.media.accepted_mime_types', ['image/png', 'application/pdf']);
    config()->set('forms.definitions', [
        'offboarding-nda' => ['key' => 'offboarding-nda', 'name' => 'Offboarding NDA'],
    ]);

    expect('forms')->toLeakNoSecrets(
        secrets: [
            // Host topology — the disk names a real bucket; only its presence is reported.
            's3-tenant-uploads',

            // Host vocabulary. A declared form's key names an internal business process, and
            // a mime list is the host's. Both are reported by size, never by entry.
            'offboarding-nda',
            'Offboarding NDA',
            'image/png',
            'application/pdf',
        ],
        mustRender: [
            // The positive proof the section reports rather than sitting empty.
            'Form model',
            'Group model',
            'Field model',
            'Submission model',
            'Aggregate model',
            'Submission review',
            // The size-not-entries lines really render their counts — which is what makes
            // hiding the entries meaningful rather than accidental.
            '1 definition(s)',
            '2 mime type(s)',
            // The disk is reported present without naming it.
            'SET',
        ],
    );
});
