<?php

declare(strict_types=1);

use RoundlyConsulting\Forms\FormsServiceProvider;
use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * Forms ships five CREATEs and three ALTERs, with eight foreign keys — the densest FK graph
 * in the fleet outside shops. `forms` is the root; `groups`, `fields`, `submissions` and
 * `form_submissions` all constrain onto it, `submissions` additionally onto `groups` and
 * `fields`, and an ALTER adds `submissions.form_submission_id -> form_submissions`.
 *
 * Publishing preserves the source order, so that order has to be runnable end to end.
 */
$migrations = __DIR__.'/../../database/migrations';

/**
 * M — the structural pin. Five packages shipped uninstallable migration orders under green
 * SQLite suites, because SQLite happily creates a table pointing at a missing parent and
 * only complains at insert time. `foreignKeys: 8` pins the edge count so the check can never
 * pass over an empty parse.
 *
 * M also pins the other, non-FK half `MigrationGraph` checks: that every `Schema::table()`
 * ALTER sorts at or after the CREATE of the table it alters (approvals #2). Forms ships
 * three ALTERs, so that half is doing real work here.
 */
it('creates every table before the migrations that reference or alter it', function () use ($migrations): void {
    expect($migrations)->toHaveRunnableMigrationOrder(foreignKeys: 8);
});

/**
 * P — the publish-only guards. The fleet publishes migrations timestamped rather than
 * auto-loading them; doing both runs both copies and dies on a duplicate table (bug #5, on
 * three packages). `8` pins the file count so neither check can pass over an empty or
 * relocated directory.
 */
it('never auto-loads its migrations — the host publishes them', function (): void {
    expect(FormsServiceProvider::class)->toNotAutoLoadMigrations();
});

it('publishes its migrations timestamp-injected into the host', function (): void {
    expect(FormsServiceProvider::class)->toPublishMigrationsTimestamped('forms-migrations', 8);
});

/**
 * R — the behavioural half, on an engine that can actually refuse. Gated on reachability so
 * it skips *visibly* off the pgsql leg rather than passing vacuously.
 *
 * `migrations: 8` pins the count, and the assertion fails hard if a set "applies cleanly"
 * while creating no tables — an empty `up()` would otherwise pass and prove nothing.
 */
it('applies the published order cleanly on postgres', function () use ($migrations): void {
    expect($migrations)->toApplyOnConnection('pgsql', migrations: 8);
})->skip(fn (): bool => ! test()->connectionAvailable('pgsql'), 'pgsql connection not available');

/**
 * The negative control. It fits here — unlike a 0-FK row, where reversing the file list
 * leaves Postgres nothing to refuse and the control fails by design. Forms has eight real FK
 * edges, so a reversed order puts children before parents and Postgres genuinely rejects it.
 */
it('is refused by postgres when the order is broken', function () use ($migrations): void {
    expect($migrations)->toRejectBrokenOrderOnConnection(
        fn (array $files): array => array_reverse($files),
        'pgsql',
    );
})->skip(fn (): bool => ! test()->connectionAvailable('pgsql'), 'pgsql connection not available');

/**
 * The driver-truth pin: the env-declared driver against what the connection itself answers.
 * It makes a lying pgsql leg impossible — a base case decapitated by an un-parented
 * `defineEnvironment()` override goes red here instead of quietly running SQLite and
 * reporting itself green.
 */
it('runs on the driver the environment declared', function (): void {
    expect(DatabaseDriver::current())->toBe(DatabaseDriver::from(DriverMatrix::driver()));
});
