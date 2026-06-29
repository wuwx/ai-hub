<?php

namespace App\Console\Commands;

use App\Models\RequestLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('gateway:prune-request-logs {--days=30 : Number of days of request logs to retain} {--dry-run : Show how many records would be deleted without actually deleting them}')]
#[Description('Delete request logs older than the configured retention window. Usage ledgers retain aggregate totals for billing.')]
class PruneRequestLogs extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = Carbon::now()->subDays($days);

        $query = RequestLog::query()->where('requested_at', '<', $cutoff);

        $count = $dryRun ? $query->count() : $query->delete();

        if ($dryRun) {
            $this->info("Dry run: would delete {$count} request logs older than {$days} days (before {$cutoff->toDateTimeString()}).");
        } else {
            $this->info("Deleted {$count} request logs older than {$days} days (before {$cutoff->toDateTimeString()}).");
        }

        return self::SUCCESS;
    }
}
