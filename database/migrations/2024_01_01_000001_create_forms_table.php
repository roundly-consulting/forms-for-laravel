<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table): void {
            $table->id();
            $table->string('key');
            $table->string('name');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            $table->softDeletes();
            // 0 while live, the row's own id once soft-deleted: a live key stays unique while
            // a trashed form gives its key up for reuse (no portable partial unique index).
            $table->unsignedBigInteger('deleted_token')->default(0);

            $table->unique(['key', 'deleted_token']);
        });
    }
};
