<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_request_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignId('customer_request_id')->constrained('customer_requests')->restrictOnDelete();
            $table->foreignId('matched_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->timestamp('matched_at');
            $table->timestamps();

            $table->unique(['merchant_id', 'customer_request_id'], 'merchant_request_matches_merchant_request_unique');
            $table->index('merchant_id');
            $table->index('customer_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_request_matches');
    }
};
