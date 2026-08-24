<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_offer_credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->tinyInteger('type');
            $table->tinyInteger('source');
            $table->integer('amount');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_request_id')->nullable()->constrained('customer_requests')->restrictOnDelete();
            $table->foreignId('merchant_offer_id')->nullable()->constrained('merchant_offers')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['merchant_id', 'customer_request_id', 'type'],
                'merchant_offer_credits_merchant_request_type_unique'
            );
            $table->index('merchant_id');
            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_offer_credit_transactions');
    }
};
