<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
     *  - forms ships its own eight.
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
        ];
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

    /**
     * A sender table for exercising morph relations and the HasForms trait. It stands in for
     * a table a host owns, so it is built here rather than shipped.
     */
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        Schema::create('submitters', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });
    }
}
