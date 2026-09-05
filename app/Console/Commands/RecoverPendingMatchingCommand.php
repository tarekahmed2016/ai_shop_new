<?php

namespace App\Console\Commands;

use App\Jobs\RecoverPendingMatchingJob;
use Illuminate\Console\Command;

/**
 * Recovers Ready requests whose matching job was never queued (process
 * crash after Ready commit) or whose matching attempt went stale.
 * Runs inline — not via the AI or default queues — so a backed-up
 * worker cannot delay the sweep. See README "Queue & scheduler".
 */
class RecoverPendingMatchingCommand extends Command
{
    protected $signature = 'customer-requests:recover-pending-matching';

    protected $description = 'Re-dispatch matching for Ready requests whose matching never completed';

    public function handle(): int
    {
        app()->call([new RecoverPendingMatchingJob, 'handle']);

        $this->info('Pending matching recovery sweep finished.');

        return self::SUCCESS;
    }
}
