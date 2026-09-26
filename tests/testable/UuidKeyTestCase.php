<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Tests\testable;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Forms\Tests\TestCase;

/**
 * The suite's base case with `forms.key_type` = `uuid` BEFORE the providers boot and the
 * migrations run — the only window that matters, since the migrations read the key type to
 * pick the `sender_id` column type. Adds the UUID-keyed sender table a host would own.
 */
abstract class UuidKeyTestCase extends TestCase
{
    /** @return array<string, mixed> */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'forms.key_type' => 'uuid',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        Schema::create('uuid_senders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->timestamps();
        });
    }
}
