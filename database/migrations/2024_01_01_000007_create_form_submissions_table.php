<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

return new class extends Migration
{
    public function up(): void
    {
        $keyType = KeyType::fromConfig('forms.key_type');

        Schema::create('form_submissions', function (Blueprint $table) use ($keyType): void {
            $table->id();
            $table->uuid()->unique();
            $table->morphKey('sender', $keyType, nullable: true);
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
