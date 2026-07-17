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

        Schema::create('submissions', function (Blueprint $table) use ($keyType): void {
            $table->id();
            $table->uuid()->index();
            $table->morphKey('sender', $keyType, nullable: true);
            $table->foreignId('form_id')->references('id')->on('forms')->onDelete('cascade');
            $table->foreignId('group_id')->references('id')->on('groups')->onDelete('cascade');
            $table->foreignId('field_id')->references('id')->on('fields')->onDelete('cascade');
            $table->jsonb('value');
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
