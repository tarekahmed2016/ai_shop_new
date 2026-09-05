<?php

namespace Tests\Support\Concurrency;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

abstract class ConcurrencyTestCase extends TestCase
{
    protected function runsConcurrentWorkers(): bool
    {
        return true;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->usesInnoDbRowLocks()) {
            return;
        }

        $this->truncateSharedConcurrencyDatabase();
    }

    private function truncateSharedConcurrencyDatabase(): void
    {
        $connection = DB::connection();
        $connection->statement('SET FOREIGN_KEY_CHECKS=0');

        foreach (Schema::getTableListing() as $table) {
            if ($table === 'migrations') {
                continue;
            }

            $connection->table($table)->truncate();
        }

        $connection->statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
