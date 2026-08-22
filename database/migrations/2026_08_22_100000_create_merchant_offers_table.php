<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_offers', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('customer_request_id')->constrained('customer_requests')->cascadeOnDelete();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->decimal('price', 12, 3);
            $table->char('currency', 3)->default('OMR');
            $table->tinyInteger('availability_status');
            $table->text('notes')->nullable();
            $table->date('valid_until')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_request_id', 'merchant_id'], 'merchant_offers_request_merchant_unique');
            $table->index('customer_request_id');
            $table->index('merchant_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_offers');
    }
};
