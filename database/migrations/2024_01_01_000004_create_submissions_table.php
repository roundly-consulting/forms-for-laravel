<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->nullableMorphs('sender');
            $table->foreignId('form_id')->references('id')->on('forms')->onDelete('cascade');
            $table->foreignId('group_id')->references('id')->on('groups')->onDelete('cascade');
            $table->foreignId('field_id')->references('id')->on('fields')->onDelete('cascade');
            $table->json('value');
            $table->timestamps();
        });
    }
};
