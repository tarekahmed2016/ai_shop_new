<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketers', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('referral_code', 16);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();

            $table->unique('user_id');
            $table->unique('referral_code');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketers');
    }
};
