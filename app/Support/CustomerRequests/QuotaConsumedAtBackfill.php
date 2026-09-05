<?php

namespace App\Support\CustomerRequests;

use App\Enums\CustomerRequests\Source;
use Illuminate\Support\Facades\DB;

/**
 * Historical parity for CustomerRequestLimitService::todayCount().
 *
 * Under the pre-async synchronous path a Web/WhatsApp row was never
 * persisted until quota/credit had already been consumed, so
 * "row exists" == "quota was consumed". Copying created_at onto
 * quota_consumed_at for those sources makes the new
 * whereNotNull('quota_consumed_at') filter return the same counts as the
 * old raw-row query for every row that existed before the pipeline.
 */
final class QuotaConsumedAtBackfill
{
    public static function run(): int
    {
        return DB::table('customer_requests')
            ->whereIn('source', [Source::Web->value, Source::WhatsApp->value])
            ->whereNull('quota_consumed_at')
            ->update(['quota_consumed_at' => DB::raw('created_at')]);
    }
}
