<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_id')->references('id')->on('forms')->cascadeOnDelete();
            $table->foreignId('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->string('name');
            $table->string('key');
            $table->string('help')->nullable();
            $table->string('type')->default('text');
            $table->string('autofill')->nullable();
            // Deliberately `json`, not `jsonb`: this is a value => label map whose KEY ORDER
            // is the select's display order (['sm' => 'Small', 'md' => 'Medium', ...]).
            // Postgres `jsonb` sorts object keys by (length, bytes), which would silently
            // reorder every author-defined dropdown. `json` preserves insertion order.
            $table->json('options')->nullable();
            $table->jsonb('validations')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['group_id', 'key']);
        });
    }
};
