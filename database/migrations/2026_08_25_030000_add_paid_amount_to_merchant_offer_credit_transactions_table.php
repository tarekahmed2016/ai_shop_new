<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_offer_credit_transactions', function (Blueprint $table) {
            $table->decimal('paid_amount', 12, 3)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('merchant_offer_credit_transactions', function (Blueprint $table) {
            $table->dropColumn('paid_amount');
        });
    }
};
