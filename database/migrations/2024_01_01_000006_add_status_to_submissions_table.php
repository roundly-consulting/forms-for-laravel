<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('submissions', 'status')) {
            Schema::table('submissions', function (Blueprint $table): void {
                $table->string('status')->nullable()->after('value');
            });
        }

        if (! Schema::hasIndex('submissions', ['status'])) {
            Schema::table('submissions', function (Blueprint $table): void {
                $table->index('status');
            });
        }
    }
};
