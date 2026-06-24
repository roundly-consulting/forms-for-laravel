<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use RoundlyConsulting\Forms\FormsServiceProvider;

abstract class TestCase extends Orchestra
{
    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [FormsServiceProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // A sender table for exercising morph relations and the HasForms trait.
        Schema::create('submitters', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });
    }
}
