<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Tests\testable;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Forms\Tests\TestCase;

/**
 * The suite's base case with `forms.key_type` = `ulid` BEFORE the providers boot and the
 * migrations run — the only window that matters, since the migrations read the key type to
 * pick the `sender_id` column type. Adds the ULID-keyed sender table a host would own.
 */
abstract class UlidKeyTestCase extends TestCase
{
    /** @return array<string, mixed> */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'forms.key_type' => 'ulid',
        ]);
    }

    /** @return list<class-string<ServiceProvider>|string> */
    protected function migrationSources(): array
    {
        return [...parent::migrationSources(), __DIR__.'/../database/ulid-senders'];
    }
}
