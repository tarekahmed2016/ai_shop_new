<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role');
            $table->tinyInteger('status')->default(1);
            $table->timestamps();

            $table->unique(['merchant_id', 'user_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_user');
    }
};
