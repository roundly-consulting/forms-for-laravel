<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Tests;

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
    }
}
