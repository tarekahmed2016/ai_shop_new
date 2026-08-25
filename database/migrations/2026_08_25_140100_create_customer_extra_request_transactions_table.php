<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_extra_request_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->tinyInteger('type');
            $table->integer('amount');
            $table->tinyInteger('source');
            $table->unsignedBigInteger('payment_transaction_id')->nullable();

            $table->foreign(
                'payment_transaction_id',
                'cer_tx_payment_fk'
            )->references('id')
                ->on('payment_transactions')
                ->restrictOnDelete();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_request_id')->nullable()->constrained('customer_requests')->restrictOnDelete();
            $table->timestamps();

            $table->unique('customer_request_id', 'customer_extra_requests_request_unique');
            $table->index('customer_id');
            $table->index('type');
            $table->index('payment_transaction_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_extra_request_transactions');
    }
};
