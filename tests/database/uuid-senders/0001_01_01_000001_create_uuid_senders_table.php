<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The UUID-keyed sender table a host would own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uuid_senders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->timestamps();
        });
    }
};
