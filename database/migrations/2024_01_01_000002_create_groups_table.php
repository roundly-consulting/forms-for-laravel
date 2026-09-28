<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_id')->references('id')->on('forms')->cascadeOnDelete();
            $table->string('name');
            $table->string('key');
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            // 0 while live, the row's own id once soft-deleted (see the forms table).
            $table->unsignedBigInteger('deleted_token')->default(0);

            $table->unique(['form_id', 'key', 'deleted_token']);
        });
    }
};
