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

    /**
     * Migrations are publish-only — no provider auto-loads anything — so the suite
     * runs them explicitly, in the same order a host gets them from the publish.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Media-library ships the `media` table submission attachments persist into.
        $this->loadMigrationsFrom($this->packagePath(MediaLibraryServiceProvider::class).'/database/migrations');

        // Attributes ships the tables its registry/casts persist into.
        $this->loadMigrationsFrom($this->packagePath(AttributesServiceProvider::class).'/database/migrations');

        // Approvals engine tables back the submission review flow (a FormSubmission is
        // an approvals subject).
        $this->loadMigrationsFrom($this->packagePath(ApprovalsServiceProvider::class).'/database/migrations');

        // A sender table for exercising morph relations and the HasForms trait.
        Schema::create('submitters', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });
    }

    /** @param  class-string  $provider */
    private function packagePath(string $provider): string
    {
        return dirname((string) (new ReflectionClass($provider))->getFileName(), 2);
    }
}
