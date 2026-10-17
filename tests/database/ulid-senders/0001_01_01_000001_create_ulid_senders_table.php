<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ULID-keyed sender table a host would own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ulid_senders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->timestamps();
        });
    }
};
