<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms;

use Illuminate\Support\ServiceProvider;

final class FormsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/forms.php', 'forms');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/forms.php' => config_path('forms.php'),
            ], 'forms-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'forms-migrations');
        }
    }
}
