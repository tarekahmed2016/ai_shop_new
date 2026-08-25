<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_offer_credit_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_transaction_id')->nullable();

            $table->foreign(
                'payment_transaction_id',
                'moc_tx_payment_fk'
            )
                ->references('id')
                ->on('payment_transactions')
                ->restrictOnDelete();
        });

    }

    public function down(): void
    {
        Schema::table('merchant_offer_credit_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_transaction_id');
        });
    }
};
