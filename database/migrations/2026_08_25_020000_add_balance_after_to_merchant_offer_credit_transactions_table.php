<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_offer_credit_transactions', function (Blueprint $table) {
            $table->integer('balance_after')->nullable()->after('amount');
        });

        $merchantIds = DB::table('merchant_offer_credit_transactions')
            ->distinct()
            ->orderBy('merchant_id')
            ->pluck('merchant_id');

        foreach ($merchantIds as $merchantId) {
            $running = 0;
            $rows = DB::table('merchant_offer_credit_transactions')
                ->where('merchant_id', $merchantId)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', 'amount']);

            foreach ($rows as $row) {
                $running += (int) $row->amount;
                DB::table('merchant_offer_credit_transactions')
                    ->where('id', $row->id)
                    ->update(['balance_after' => $running]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('merchant_offer_credit_transactions', function (Blueprint $table) {
            $table->dropColumn('balance_after');
        });
    }
};
