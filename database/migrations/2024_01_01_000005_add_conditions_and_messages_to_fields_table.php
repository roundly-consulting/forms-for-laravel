<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fields', function (Blueprint $table): void {
            if (! Schema::hasColumn('fields', 'conditions')) {
                $table->jsonb('conditions')->nullable()->after('validations');
            }

            if (! Schema::hasColumn('fields', 'messages')) {
                $table->jsonb('messages')->nullable()->after('conditions');
            }
        });
    }
};
