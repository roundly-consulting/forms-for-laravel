<?php

declare(strict_types=1);

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\FormsManager;
use RoundlyConsulting\Forms\FormsServiceProvider;
use RoundlyConsulting\Forms\Models\Form;

it('registers the forms manager as a singleton', function (): void {
    expect(app(FormsManager::class))->toBeInstanceOf(FormsManager::class)
        ->and(app(FormsManager::class))->toBe(app(FormsManager::class))
        ->and(Forms::getFacadeRoot())->toBe(app(FormsManager::class));
});

it('merges the packaged config', function (): void {
    expect(config('forms.models.form'))->toBe(Form::class)
        ->and(config('forms.media.bucket'))->toBe('attachment');
});

it('registers the sync command', function (): void {
    expect(array_keys(app('Illuminate\Contracts\Console\Kernel')->all()))->toContain('forms:sync');
});

it('aliases the facade', function (): void {
    expect(AliasLoader::getInstance()->getAliases())->toHaveKey('Forms', Forms::class);
});

it('loads the package translations', function (): void {
    expect(trans('forms::messages.form_not_found'))->not->toBe('forms::messages.form_not_found');
});

/**
 * Publish-only migrations (fleet policy). A bare `php artisan migrate` in a host must
 * NOT create the package's tables — the host publishes them first. This pins the
 * policy against a regression that re-adds `loadMigrationsFrom()`.
 */
it('never auto-loads its migrations', function (): void {
    $packageMigrations = realpath(__DIR__.'/../../database/migrations');

    $loaded = array_map(
        static fn (string $path): string => (string) realpath($path),
        app('migrator')->paths(),
    );

    expect($loaded)->not->toContain($packageMigrations);
});

it('publishes each migration into the host migrations directory under a timestamped name', function (): void {
    $paths = ServiceProvider::pathsToPublish(FormsServiceProvider::class, 'forms-migrations');

    expect($paths)->toHaveCount(8);

    foreach ($paths as $source => $target) {
        expect($source)->toEndWith('.php')
            ->and(dirname((string) $target))->toBe(database_path('migrations'))
            ->and(basename((string) $target))->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_\w+\.php$/');
    }
});

/**
 * Eight migrations, and every one of the four child tables carries a real foreign
 * key. Publishing preserves the source directory's order, so the published
 * timestamps have to sort into the dependency order a host migrates in.
 */
it('publishes timestamps that preserve the dependency order', function (): void {
    $destinations = array_map(
        static fn (string $target): string => basename($target),
        array_values(ServiceProvider::pathsToPublish(FormsServiceProvider::class, 'forms-migrations')),
    );

    $sorted = $destinations;
    sort($sorted);

    expect($sorted)->toBe($destinations);

    $position = static function (string $needle) use ($destinations): int {
        foreach ($destinations as $index => $name) {
            if (str_contains($name, $needle)) {
                return $index;
            }
        }

        return -1;
    };

    // Foreign-key targets are created before every table that references them.
    expect($position('create_forms_table'))->toBeLessThan($position('create_groups_table'))
        ->and($position('create_forms_table'))->toBeLessThan($position('create_fields_table'))
        ->and($position('create_groups_table'))->toBeLessThan($position('create_fields_table'))
        ->and($position('create_fields_table'))->toBeLessThan($position('create_submissions_table'))
        ->and($position('create_form_submissions_table'))
        ->toBeLessThan($position('add_form_submission_id_to_submissions_table'));

    // Each ALTER sorts after the CREATE of the table it alters.
    expect($position('create_fields_table'))
        ->toBeLessThan($position('add_conditions_and_messages_to_fields_table'))
        ->and($position('create_submissions_table'))
        ->toBeLessThan($position('add_status_to_submissions_table'))
        ->and($position('create_submissions_table'))
        ->toBeLessThan($position('add_form_submission_id_to_submissions_table'));
});

it('keeps every publish tag byte-identical', function (): void {
    $config = ServiceProvider::pathsToPublish(FormsServiceProvider::class, 'forms-config');
    expect(array_values($config))->toBe([config_path('forms.php')]);

    $translations = ServiceProvider::pathsToPublish(FormsServiceProvider::class, 'forms-translations');
    expect(array_values($translations))->toBe([app()->langPath('vendor/forms')]);
});

it('contributes a forms section to about', function (): void {
    $this->artisan('about', ['--only' => 'forms'])
        ->expectsOutputToContain('Form model')
        ->expectsOutputToContain('Submission review')
        ->assertExitCode(0);
});

/**
 * A form's field keys and a submission's values are the host's own (often personal)
 * data, and the attachment disk is its filesystem topology. None of it may reach
 * `about` — the section reports counts, switches and bounds only.
 */
it('never renders host vocabulary or topology in the about section', function (): void {
    config()->set('forms.media.disk', 'tenant-private-uploads');
    config()->set('forms.media.accepted_mime_types', ['image/png', 'application/x-medical-record']);
    config()->set('forms.definitions', [['key' => 'hiv-screening-intake', 'name' => 'Intake', 'groups' => []]]);
    config()->set('forms.field_types', ['diagnosis_code' => 'string']);

    $this->artisan('about', ['--only' => 'forms'])
        ->doesntExpectOutputToContain('tenant-private-uploads')
        ->doesntExpectOutputToContain('application/x-medical-record')
        ->doesntExpectOutputToContain('hiv-screening-intake')
        ->doesntExpectOutputToContain('diagnosis_code')
        ->assertExitCode(0);
});

it('reports configured lists as counts', function (): void {
    config()->set('forms.media.accepted_mime_types', ['image/png', 'image/jpeg']);

    $this->artisan('about', ['--only' => 'forms'])
        ->expectsOutputToContain('2 mime type(s)')
        ->assertExitCode(0);
});

it('reports the attachment disk as presence only', function (): void {
    config()->set('forms.media.disk', null);

    $this->artisan('about', ['--only' => 'forms'])
        ->expectsOutputToContain('MEDIA DEFAULT')
        ->assertExitCode(0);
});
