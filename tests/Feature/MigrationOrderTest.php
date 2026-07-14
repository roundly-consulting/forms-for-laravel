<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Forms\FormsServiceProvider;

/**
 * The package ships five CREATEs and three ALTERs, and every child table carries a
 * real foreign key (`groups`→`forms`, `fields`→`forms`+`groups`,
 * `submissions`→`forms`+`groups`+`fields`+`form_submissions`,
 * `form_submissions`→`forms`). Publishing preserves the source directory's order, so
 * that order has to be runnable end to end from an empty database: every table must
 * exist before anything references or alters it.
 *
 * SQLite happily creates a table referencing a missing parent (it only complains at
 * insert time), so these tests are the *committed* pin; the order was additionally
 * proved against a real PostgreSQL server, which rejects a dangling foreign key at
 * DDL time.
 *
 * These tests run the *published* files — under their published names, into a
 * database that starts empty — which is what a host actually does.
 */
beforeEach(function (): void {
    $this->publishedPath = sys_get_temp_dir().'/forms-migration-order-'.bin2hex(random_bytes(6));
    $this->publishedDatabase = $this->publishedPath.'/database.sqlite';

    File::makeDirectory($this->publishedPath, recursive: true);
    File::put($this->publishedDatabase, '');

    foreach (ServiceProvider::pathsToPublish(FormsServiceProvider::class, 'forms-migrations') as $source => $target) {
        File::copy($source, $this->publishedPath.'/'.basename((string) $target));
    }

    config()->set('database.connections.published', [
        'driver' => 'sqlite',
        'database' => $this->publishedDatabase,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
});

afterEach(function (): void {
    File::deleteDirectory($this->publishedPath);
});

it('migrates the published files clean from an empty database', function (): void {
    $schema = Schema::connection('published');

    expect($schema->hasTable('forms'))->toBeFalse();

    $this->artisan('migrate', [
        '--database' => 'published',
        '--path' => $this->publishedPath,
        '--realpath' => true,
    ])->assertExitCode(0);

    expect($schema->hasTable('forms'))->toBeTrue()
        ->and($schema->hasTable('groups'))->toBeTrue()
        ->and($schema->hasTable('fields'))->toBeTrue()
        ->and($schema->hasTable('submissions'))->toBeTrue()
        ->and($schema->hasTable('form_submissions'))->toBeTrue();

    // Every ALTER ran against a table that already existed.
    expect($schema->hasColumns('fields', ['conditions', 'messages']))->toBeTrue()
        ->and($schema->hasColumns('submissions', ['status', 'form_submission_id']))->toBeTrue();
});

it('keeps every foreign key intact in the published schema', function (): void {
    $this->artisan('migrate', [
        '--database' => 'published',
        '--path' => $this->publishedPath,
        '--realpath' => true,
    ])->assertExitCode(0);

    $schema = Schema::connection('published');

    $references = static fn (string $table): array => array_map(
        static fn (array $key): string => (string) $key['foreign_table'],
        $schema->getForeignKeys($table),
    );

    // The CREATE order is load-bearing, not incidental: each child really does
    // constrain onto the tables created before it.
    expect($references('groups'))->toContain('forms')
        ->and($references('fields'))->toContain('forms', 'groups')
        ->and($references('form_submissions'))->toContain('forms')
        ->and($references('submissions'))->toContain('forms', 'groups', 'fields', 'form_submissions');
});
