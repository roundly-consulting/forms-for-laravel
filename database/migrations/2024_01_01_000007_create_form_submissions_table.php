<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->nullableMorphs('sender');
            $table->foreignId('form_id')->references('id')->on('forms')->onDelete('cascade');
            $table->string('status')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
