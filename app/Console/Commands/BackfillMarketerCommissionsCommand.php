<?php

namespace App\Console\Commands;

use App\Services\MarketerCommissionService;
use Illuminate\Console\Command;

class BackfillMarketerCommissionsCommand extends Command
{
    protected $signature = 'marketers:backfill-commissions';

    protected $description = 'Idempotently create marketer commissions for existing eligible Paid payment transactions';

    public function handle(MarketerCommissionService $commissionService): int
    {
        $created = $commissionService->backfillExistingPaidPayments();

        $this->info("Created {$created} marketer commission(s).");

        return self::SUCCESS;
    }
}
