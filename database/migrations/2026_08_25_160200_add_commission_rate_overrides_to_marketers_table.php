<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketers', function (Blueprint $table) {
            $table->decimal('customer_commission_rate', 6, 3)->nullable()->after('status');
            $table->decimal('merchant_commission_rate', 6, 3)->nullable()->after('customer_commission_rate');
        });
    }

    public function down(): void
    {
        Schema::table('marketers', function (Blueprint $table) {
            $table->dropColumn(['customer_commission_rate', 'merchant_commission_rate']);
        });
    }
};
