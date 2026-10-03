<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/**
 * `forms.key_type` sets the sender morph columns at first migrate. A typo there used to
 * migrate quietly as bigint and surface later as a uuid sender that cannot be stored;
 * now the migration refuses it before a table is touched.
 */
it('refuses a key type typo before creating a sender column (strict config)', function (string $migration): void {
    config()->set('forms.key_type', 'uiid');

    $up = (require __DIR__.'/../../database/migrations/'.$migration)->up(...);

    expect($up)->toThrow(InvalidConfigurationException::class, 'forms.key_type');
})->with([
    '2024_01_01_000004_create_submissions_table.php',
    '2024_01_01_000007_create_form_submissions_table.php',
]);
