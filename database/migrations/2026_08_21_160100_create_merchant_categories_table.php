<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['merchant_id', 'category_id']);
            $table->index('merchant_id');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_categories');
    }
};
