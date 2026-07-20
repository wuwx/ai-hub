<?php

namespace App\Http\Controllers;

use App\Models\LlmProvider;
use App\Models\User;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;
use Revoltify\Subscriptionify\Enums\SubscriptionStatus;
use Revoltify\Subscriptionify\Models\Subscription;

class PrometheusMetricsController extends Controller
{
    public function __invoke(): Response
    {
        $metrics = [];

        // User and API token counts
        $totalUsers = User::count();
        $totalApiKeys = PersonalAccessToken::count();
        $activeApiKeys = PersonalAccessToken::where(function ($query) {
            $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        })->count();

        $metrics[] = '# TYPE ai_hub_users gauge';
        $metrics[] = "ai_hub_users {$totalUsers}";
        $metrics[] = '# TYPE ai_hub_api_keys gauge';
        $metrics[] = "ai_hub_api_keys {$totalApiKeys}";
        $metrics[] = '# TYPE ai_hub_api_keys_active gauge';
        $metrics[] = "ai_hub_api_keys_active {$activeApiKeys}";

        // Provider availability
        $providers = LlmProvider::all();
        $metrics[] = '# TYPE ai_hub_provider_active gauge';
        foreach ($providers as $provider) {
            $value = $provider->is_active ? 1 : 0;
            $metrics[] = "ai_hub_provider_active{provider=\"{$provider->slug}\"} {$value}";
        }

        // Billing metrics
        $activeSubscriptions = Subscription::where(
            'status',
            SubscriptionStatus::Active,
        )->count();
        $trialingSubscriptions = Subscription::where(
            'status',
            SubscriptionStatus::Trialing,
        )->count();
        $pastDueSubscriptions = Subscription::where(
            'status',
            SubscriptionStatus::PastDue,
        )->count();

        $metrics[] = '# TYPE ai_hub_subscriptions gauge';
        $metrics[] = "ai_hub_subscriptions{status=\"active\"} {$activeSubscriptions}";
        $metrics[] = "ai_hub_subscriptions{status=\"trialing\"} {$trialingSubscriptions}";
        $metrics[] = "ai_hub_subscriptions{status=\"past_due\"} {$pastDueSubscriptions}";

        $output = implode("\n", $metrics)."\n";

        return response($output, 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=UTF-8',
        ]);
    }
}
