<?php

namespace App\Console\Commands;

use App\Jobs\RecoverStuckAiRequestsJob;
use Illuminate\Console\Command;

/**
 * Runs stuck-AI recovery and idle-attempt expiry inline (not via the
 * AI processing queue). This command is the only production entry point
 * for RecoverStuckAiRequestsJob: Laravel's scheduler must invoke it.
 * If cron/`schedule:work` is not running, stuck rows are never recovered
 * and abandoned ready_for_review/failed rows are never expired.
 */
class RecoverStuckAiRequestsCommand extends Command
{
    protected $signature = 'customer-requests:recover-stuck-ai';

    protected $description = 'Recover customer requests stuck mid AI pipeline and expire abandoned open attempts';

    public function handle(): int
    {
        app()->call([new RecoverStuckAiRequestsJob, 'handle']);

        $this->info('Stuck AI request recovery sweep finished.');

        return self::SUCCESS;
    }
}
