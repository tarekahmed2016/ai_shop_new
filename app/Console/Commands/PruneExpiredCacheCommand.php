<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deletes expired database-cache rows and expired cache locks.
 *
 * Laravel's database cache store leaves expired keys in place until that
 * key is read again. Notification idempotency keys (1 day) and duplicate
 * notices would otherwise accumulate forever. Safe: live keys and
 * unexpired locks are not touched.
 */
class PruneExpiredCacheCommand extends Command
{
    protected $signature = 'cache:prune-expired';

    protected $description = 'Delete expired rows from the database cache and cache_locks tables';

    public function handle(): int
    {
        $now = time();
        $cacheTable = (string) config('cache.stores.database.table', 'cache');
        $lockTable = (string) (config('cache.stores.database.lock_table') ?: 'cache_locks');

        $cacheDeleted = DB::table($cacheTable)->where('expiration', '<', $now)->delete();
        $lockDeleted = DB::table($lockTable)->where('expiration', '<', $now)->delete();

        $this->info("Pruned {$cacheDeleted} expired cache row(s) and {$lockDeleted} expired lock(s).");

        return self::SUCCESS;
    }
}
