<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp_id')->nullable()->unique();
            $table->string('email')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();

            $table->index('status');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
