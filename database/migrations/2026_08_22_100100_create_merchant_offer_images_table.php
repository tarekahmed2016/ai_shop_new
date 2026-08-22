<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_offer_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_offer_id')->constrained('merchant_offers')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('merchant_offer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_offer_images');
    }
};
