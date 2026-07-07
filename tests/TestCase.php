<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use ReflectionClass;
use RoundlyConsulting\Approvals\ApprovalsServiceProvider;
use RoundlyConsulting\Attributes\AttributesServiceProvider;
use RoundlyConsulting\Forms\FormsServiceProvider;
use RoundlyConsulting\Forms\Testing\InteractsWithForms;
use RoundlyConsulting\MediaLibrary\MediaLibraryServiceProvider;

abstract class TestCase extends Orchestra
{
    use InteractsWithForms;

    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            ApprovalsServiceProvider::class,
            AttributesServiceProvider::class,
            MediaLibraryServiceProvider::class,
            FormsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Media-library: store on a fakeable public disk, use the GD driver, and keep the
        // responsive ladder small so variant generation stays fast under test.
        $app['config']->set('media.disk', 'public');
        $app['config']->set('media.image_driver', 'gd');
        $app['config']->set('media.responsive.widths', [320, 640]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Media-library ships the `media` table submission attachments persist into.
        $mediaPackage = dirname((string) (new ReflectionClass(MediaLibraryServiceProvider::class))->getFileName(), 2);
        $this->loadMigrationsFrom($mediaPackage.'/database/migrations');

        // Attributes ships the tables its registry/casts persist into.
        $attributesPackage = dirname((string) (new ReflectionClass(AttributesServiceProvider::class))->getFileName(), 2);
        $this->loadMigrationsFrom($attributesPackage.'/database/migrations');

        // Approvals engine tables back the submission review flow.
        $this->loadApprovalsSchema();

        // A sender table for exercising morph relations and the HasForms trait.
        Schema::create('submitters', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });
    }

    /**
     * Run the approvals engine migrations in dependency order; their tables back
     * the submission review flow (a FormSubmission is an approvals subject).
     */
    private function loadApprovalsSchema(): void
    {
        $base = dirname((string) (new ReflectionClass(ApprovalsServiceProvider::class))->getFileName(), 2);

        $migrations = [
            'create_approvals_table',
            'create_approval_requests_table',
            'add_v11_columns_to_approvals_table',
            'add_staging_to_approval_requests_table',
            'create_approval_request_stages_table',
            'create_approval_delegations_table',
        ];

        foreach ($migrations as $name) {
            $migration = require "{$base}/database/migrations/{$name}.php";

            if (is_object($migration) && method_exists($migration, 'up')) {
                $migration->up();
            }
        }
    }
}
