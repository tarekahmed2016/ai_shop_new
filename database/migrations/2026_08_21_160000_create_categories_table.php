<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('slug')->nullable()->unique();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->tinyInteger('status')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('status');
            $table->index('parent_id');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
