<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\LlmProvider;
use App\Models\RequestLog;
use App\Models\User;
use Illuminate\Http\Response;
use Laravel\Cashier\Subscription;

class PrometheusMetricsController extends Controller
{
    public function __invoke(): Response
    {
        $metrics = [];

        // Gateway request metrics
        $totalRequests = RequestLog::count();
        $totalErrors = RequestLog::where('status_code', '>=', 400)->count();
        $todayRequests = RequestLog::whereDate(
            'requested_at',
            today(),
        )->count();
        $todayErrors = RequestLog::whereDate('requested_at', today())
            ->where('status_code', '>=', 400)
            ->count();

        $metrics[] = '# TYPE ai_hub_requests_total counter';
        $metrics[] = "ai_hub_requests_total {$totalRequests}";
        $metrics[] = '# TYPE ai_hub_requests_errors_total counter';
        $metrics[] = "ai_hub_requests_errors_total {$totalErrors}";
        $metrics[] = '# TYPE ai_hub_requests_today gauge';
        $metrics[] = "ai_hub_requests_today {$todayRequests}";
        $metrics[] = '# TYPE ai_hub_requests_errors_today gauge';
        $metrics[] = "ai_hub_requests_errors_today {$todayErrors}";

        // Token metrics
        $totalTokensInput = (int) RequestLog::sum('token_input');
        $totalTokensOutput = (int) RequestLog::sum('token_output');

        $metrics[] = '# TYPE ai_hub_tokens_input_total counter';
        $metrics[] = "ai_hub_tokens_input_total {$totalTokensInput}";
        $metrics[] = '# TYPE ai_hub_tokens_output_total counter';
        $metrics[] = "ai_hub_tokens_output_total {$totalTokensOutput}";

        // User and API key counts
        $totalUsers = User::count();
        $totalApiKeys = ApiKey::count();
        $activeApiKeys = ApiKey::whereNull('revoked_at')
            ->where(function ($query) {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();

        $metrics[] = '# TYPE ai_hub_users gauge';
        $metrics[] = "ai_hub_users {$totalUsers}";
        $metrics[] = '# TYPE ai_hub_api_keys gauge';
        $metrics[] = "ai_hub_api_keys {$totalApiKeys}";
        $metrics[] = '# TYPE ai_hub_api_keys_active gauge';
        $metrics[] = "ai_hub_api_keys_active {$activeApiKeys}";

        // Provider health
        $providers = LlmProvider::all();
        $metrics[] = '# TYPE ai_hub_provider_active gauge';
        foreach ($providers as $provider) {
            $value = $provider->is_active ? 1 : 0;
            $metrics[] = "ai_hub_provider_active{provider=\"{$provider->slug}\"} {$value}";
        }

        $metrics[] = '# TYPE ai_hub_provider_health gauge';
        foreach ($providers as $provider) {
            $status = $provider->last_health_status ?? 'unknown';
            $value = $status === 'healthy' ? 1 : 0;
            $metrics[] = "ai_hub_provider_health{provider=\"{$provider->slug}\",status=\"{$status}\"} {$value}";
        }

        // Billing metrics
        $activeSubscriptions = Subscription::where(
            'stripe_status',
            'active',
        )->count();
        $trialingSubscriptions = Subscription::where(
            'stripe_status',
            'trialing',
        )->count();
        $pastDueSubscriptions = Subscription::where(
            'stripe_status',
            'past_due',
        )->count();

        $metrics[] = '# TYPE ai_hub_subscriptions gauge';
        $metrics[] = "ai_hub_subscriptions{status=\"active\"} {$activeSubscriptions}";
        $metrics[] = "ai_hub_subscriptions{status=\"trialing\"} {$trialingSubscriptions}";
        $metrics[] = "ai_hub_subscriptions{status=\"past_due\"} {$pastDueSubscriptions}";

        // Latency metrics (average over last 24h)
        $avgLatency =
            (int) RequestLog::where('requested_at', '>=', now()->subDay())->avg(
                'latency_ms',
            ) ?? 0;

        $metrics[] = '# TYPE ai_hub_request_latency_ms_avg gauge';
        $metrics[] = "ai_hub_request_latency_ms_avg {$avgLatency}";

        $output = implode("\n", $metrics)."\n";

        return response($output, 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=UTF-8',
        ]);
    }
}
