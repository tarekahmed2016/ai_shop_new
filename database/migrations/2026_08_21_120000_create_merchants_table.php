<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
