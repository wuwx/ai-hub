<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\BillingInvoice;
use App\Models\LlmProvider;
use App\Models\RequestLog;
use App\Models\Team;
use App\Models\TeamWallet;
use Illuminate\Http\Response;

class PrometheusMetricsController extends Controller
{
    public function __invoke(): Response
    {
        $metrics = [];

        // Gateway request metrics
        $totalRequests = RequestLog::count();
        $totalErrors = RequestLog::where('status_code', '>=', 400)->count();
        $todayRequests = RequestLog::whereDate('requested_at', today())->count();
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

        // Team and API key counts
        $totalTeams = Team::count();
        $totalApiKeys = ApiKey::count();
        $activeApiKeys = ApiKey::whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();

        $metrics[] = '# TYPE ai_hub_teams gauge';
        $metrics[] = "ai_hub_teams {$totalTeams}";
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
        $totalWalletBalance = (int) TeamWallet::where('is_postpaid', false)->sum('balance_cents');
        $overdueInvoices = BillingInvoice::where('status', 'overdue')->count();
        $paidInvoices = BillingInvoice::where('status', 'paid')->count();
        $issuedInvoices = BillingInvoice::where('status', 'issued')->count();

        $metrics[] = '# TYPE ai_hub_wallet_balance_cents gauge';
        $metrics[] = "ai_hub_wallet_balance_cents {$totalWalletBalance}";
        $metrics[] = '# TYPE ai_hub_invoices gauge';
        $metrics[] = "ai_hub_invoices{status=\"overdue\"} {$overdueInvoices}";
        $metrics[] = "ai_hub_invoices{status=\"paid\"} {$paidInvoices}";
        $metrics[] = "ai_hub_invoices{status=\"issued\"} {$issuedInvoices}";

        // Latency metrics (average over last 24h)
        $avgLatency = (int) RequestLog::where('requested_at', '>=', now()->subDay())
            ->avg('latency_ms') ?? 0;

        $metrics[] = '# TYPE ai_hub_request_latency_ms_avg gauge';
        $metrics[] = "ai_hub_request_latency_ms_avg {$avgLatency}";

        $output = implode("\n", $metrics)."\n";

        return response($output, 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=UTF-8',
        ]);
    }
}
