<?php

use App\Enums\CustomerRequests\Status as RequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Durable matching-recovery marker for the lost-dispatch window:
 * Ready is committed, then the process dies before MatchCustomerRequestJob
 * is inserted into the jobs table.
 *
 * matching_completed_at null on a Ready row means matching still required.
 * Historical Ready rows are backfilled as completed so they are not
 * swept by the new recovery command.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_requests', function (Blueprint $table) {
            $table->timestamp('matching_completed_at')->nullable()->after('duplicate_of_customer_request_id');
            $table->timestamp('matching_last_attempt_at')->nullable()->after('matching_completed_at');

            $table->index(
                ['status', 'matching_completed_at', 'matching_last_attempt_at'],
                'customer_requests_matching_pending_idx',
            );
        });

        DB::table('customer_requests')
            ->where('status', RequestStatus::Ready->value)
            ->whereNull('matching_completed_at')
            ->update([
                'matching_completed_at' => DB::raw('updated_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('customer_requests', function (Blueprint $table) {
            $table->dropIndex('customer_requests_matching_pending_idx');
            $table->dropColumn([
                'matching_completed_at',
                'matching_last_attempt_at',
            ]);
        });
    }
};
