<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Tests;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Approvals\ApprovalsServiceProvider;
use RoundlyConsulting\Attributes\AttributesServiceProvider;
use RoundlyConsulting\Forms\FormsServiceProvider;
use RoundlyConsulting\Forms\Testing\InteractsWithForms;
use RoundlyConsulting\MediaLibrary\MediaLibraryServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    use InteractsWithForms;

    /**
     * Every provider forms hard-requires, in registration order. A host auto-discovers
     * these; the suite must list them or the test environment is a fiction.
     *
     * @return list<class-string<ServiceProvider>>
     */
    protected function packageProviders(): array
    {
        return [
            ApprovalsServiceProvider::class,
            AttributesServiceProvider::class,
            MediaLibraryServiceProvider::class,
            FormsServiceProvider::class,
        ];
    }

    /**
     * Migration sources by provider class, never by filename:
     *  - media-library ships the `media` table submission attachments persist into;
     *  - attributes ships the tables its registry/casts persist into;
     *  - approvals' engine tables back the submission review flow (a FormSubmission is an
     *    approvals subject);
     *  - forms ships its own eight;
     *  - the suite's `submitters` sender table, standing in for a table a host owns (a
     *    source, not a create after the migrate, so a kept schema stays kept).
     *
     * @return list<class-string<ServiceProvider>|string>
     */
    protected function migrationSources(): array
    {
        return [
            MediaLibraryServiceProvider::class,
            AttributesServiceProvider::class,
            ApprovalsServiceProvider::class,
            FormsServiceProvider::class,
            __DIR__.'/database/migrations',
        ];
    }

    /**
     * Migrate once per worker and schema signature, then reset only the data between tests.
     */
    protected function keepsMigratedSchema(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return [
            // Media-library: store on a fakeable public disk, use the GD driver, and keep the
            // responsive ladder small so variant generation stays fast under test.
            'media.disk' => 'public',
            'media.image_driver' => 'gd',
            'media.responsive.widths' => [320, 640],
        ];
    }
}
