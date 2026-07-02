<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use App\Models\RequestLog;
use App\Models\User;
use App\Notifications\AnomalousUsageDetected;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

#[Signature('gateway:detect-anomalous-usage {--window=60 : Analysis window in minutes} {--min-requests=50 : Minimum requests to flag} {--error-rate=50 : Error-rate percentage threshold} {--dedupe-hours=6 : Suppress repeat alerts for the same API key within this window}')]
#[Description('Scan recent traffic for anomalous API key usage (high error rate, traffic spikes). Notifies users.')]
class DetectAnomalousUsage extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $windowMinutes = max(1, (int) $this->option('window'));
        $minRequests = max(1, (int) $this->option('min-requests'));
        $errorRateThreshold = max(0, (int) $this->option('error-rate'));
        $dedupeHours = max(1, (int) $this->option('dedupe-hours'));

        $since = now()->subMinutes($windowMinutes);

        // Aggregate per API key + user in the window
        $rows = RequestLog::query()
            ->select([
                'api_key_id',
                'user_id',
                DB::raw('COUNT(*) as request_count'),
                DB::raw('SUM(CASE WHEN status_code >= 400 OR error_code IS NOT NULL THEN 1 ELSE 0 END) as error_count'),
                DB::raw('SUM(token_total) as tokens'),
            ])
            ->where('requested_at', '>=', $since)
            ->whereNotNull('api_key_id')
            ->groupBy('api_key_id', 'user_id')
            ->having('request_count', '>=', $minRequests)
            ->get();

        if ($rows->isEmpty()) {
            $this->info("No API key reached {$minRequests} requests in the last {$windowMinutes}m.");

            return self::SUCCESS;
        }

        $flagged = 0;
        $suppressed = 0;

        foreach ($rows as $row) {
            $errorRate = $row->request_count > 0 ? ($row->error_count / $row->request_count) * 100 : 0;

            if ($errorRate < $errorRateThreshold) {
                continue;
            }

            $user = User::find($row->user_id);
            $apiKey = ApiKey::find($row->api_key_id);

            if (! $user || ! $apiKey) {
                continue;
            }

            // Dedupe: suppress repeat notifications for the same API key within
            // the configured window so a persistently failing key doesn't spam
            // the user every hour.
            $dedupeKey = sprintf('gateway:anomaly-alert:%d', $apiKey->id);

            if (! Cache::add($dedupeKey, true, now()->addHours($dedupeHours))) {
                $suppressed++;

                continue;
            }

            Notification::send($user, new AnomalousUsageDetected(
                userName: $user->name,
                apiKeyName: $apiKey->name,
                requestCount: (int) $row->request_count,
                errorCount: (int) $row->error_count,
                errorRate: $errorRate,
                windowMinutes: $windowMinutes,
            ));

            $flagged++;
        }

        $this->info("Anomaly scan complete: {$flagged} API key(s) flagged, {$suppressed} duplicate(s) suppressed.");

        return self::SUCCESS;
    }
}
