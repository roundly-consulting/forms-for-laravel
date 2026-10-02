<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/*
 * Migrations are forward-only: a `down()` is dead code that drifts out of sync with `up()`,
 * and testing-for-laravel does not pin its absence. Migrations 5–8 shipped one each, so this
 * file pins it here, and pins that the ALTERs (5, 6 and 8) survive a re-run — with no
 * `down()`, `migrate:rollback` leaves their columns behind and the next `migrate` runs them
 * again over the top.
 */

/**
 * @return array<string, Migration>
 */
function formsMigrations(): array
{
    $migrations = [];

    foreach (glob(__DIR__.'/../../database/migrations/*.php') ?: [] as $path) {
        $migrations[basename($path, '.php')] = require $path;
    }

    return $migrations;
}

function formsMigration(string $suffix): Migration
{
    foreach (formsMigrations() as $name => $migration) {
        if (str_ends_with($name, $suffix)) {
            return $migration;
        }
    }

    throw new RuntimeException("No forms migration ending in {$suffix}.");
}

it('ships no down() in any migration', function (): void {
    $migrations = formsMigrations();

    expect($migrations)->toHaveCount(8);

    foreach ($migrations as $name => $migration) {
        expect(method_exists($migration, 'down'))->toBeFalse("{$name} defines down()");
    }
});

it('re-runs every ALTER migration over an already-migrated schema', function (): void {
    formsMigration('000005_add_conditions_and_messages_to_fields_table')->up();
    formsMigration('000006_add_status_to_submissions_table')->up();
    formsMigration('000008_add_form_submission_id_to_submissions_table')->up();

    expect(Schema::hasColumns('fields', ['conditions', 'messages']))->toBeTrue()
        ->and(Schema::hasColumns('submissions', ['status', 'form_submission_id']))->toBeTrue()
        ->and(collect(Schema::getIndexes('submissions'))->where('columns', ['status']))->toHaveCount(1)
        ->and(collect(Schema::getForeignKeys('submissions'))->where('columns', ['form_submission_id']))->toHaveCount(1);
});

it('restores the status index when only the column survived', function (): void {
    Schema::table('submissions', function ($table): void {
        $table->dropIndex(['status']);
    });

    formsMigration('000006_add_status_to_submissions_table')->up();

    expect(Schema::hasIndex('submissions', ['status']))->toBeTrue();
});
