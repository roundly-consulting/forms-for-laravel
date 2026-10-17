<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A sender table for exercising morph relations and the HasForms trait. It stands in for a
 * table a host owns, so it lives in the suite rather than shipping.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submitters', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });
    }
};
