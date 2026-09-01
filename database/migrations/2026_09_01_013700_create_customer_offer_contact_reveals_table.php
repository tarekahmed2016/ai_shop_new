<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_offer_contact_reveals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_request_id');
            $table->unsignedBigInteger('merchant_offer_id')->nullable();
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('customer_id');
            $table->timestamp('revealed_at');
            $table->timestamps();

            $table->foreign('customer_request_id', 'cocr_request_fk')
                ->references('id')
                ->on('customer_requests')
                ->cascadeOnDelete();

            $table->foreign('merchant_offer_id', 'cocr_offer_fk')
                ->references('id')
                ->on('merchant_offers')
                ->nullOnDelete();

            $table->foreign('merchant_id', 'cocr_merchant_fk')
                ->references('id')
                ->on('merchants')
                ->restrictOnDelete();

            $table->foreign('customer_id', 'cocr_customer_fk')
                ->references('id')
                ->on('customers')
                ->restrictOnDelete();

            $table->unique(['customer_request_id', 'merchant_id'], 'cocr_request_merchant_uq');
            $table->index('merchant_id', 'cocr_merchant_idx');
            $table->index('customer_id', 'cocr_customer_idx');
            $table->index('merchant_offer_id', 'cocr_offer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_offer_contact_reveals');
    }
};
