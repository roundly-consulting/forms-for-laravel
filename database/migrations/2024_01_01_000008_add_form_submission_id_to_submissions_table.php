<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('submissions', 'form_submission_id')) {
            return;
        }

        Schema::table('submissions', function (Blueprint $table): void {
            $table->foreignId('form_submission_id')
                ->nullable()
                ->after('uuid')
                ->constrained('form_submissions')
                ->nullOnDelete();
        });
    }
};
