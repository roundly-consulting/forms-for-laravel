<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Forms\Commands\SyncFormsCommand;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\Services\FormsService;

final class FormsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/forms.php', 'forms');

        $this->app->singleton('forms.manager', fn (): FormsService => new FormsService);
        $this->app->alias('forms.manager', FormsService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'forms');

        AliasLoader::getInstance()->alias('Forms', Forms::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncFormsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/forms.php' => config_path('forms.php'),
            ], 'forms-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'forms-migrations');

            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/forms'),
            ], 'forms-translations');
        }
    }
}
