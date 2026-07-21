<?php

namespace App\Providers;

use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Revoltify\Subscriptionify\Enums\SubscriptionStatus;
use Revoltify\Subscriptionify\Models\Subscription;
use Spatie\Prometheus\Facades\Prometheus;

class PrometheusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Prometheus::addGauge('users')
            ->helpText('Total number of registered users')
            ->value(fn () => User::count());

        Prometheus::addGauge('api_keys')
            ->helpText('Total number of API keys')
            ->value(fn () => PersonalAccessToken::count());

        Prometheus::addGauge('api_keys_active')
            ->helpText('Number of active (non-expired) API keys')
            ->value(fn () => PersonalAccessToken::where(
                fn ($query) => $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now()),
            )->count());

        Prometheus::addGauge('provider_active')
            ->helpText('Provider availability status')
            ->label('provider')
            ->value(function () {
                return AiProvider::all()
                    ->map(fn (AiProvider $provider) => [
                        $provider->is_active ? 1 : 0,
                        [$provider->slug],
                    ])
                    ->all();
            });

        Prometheus::addGauge('subscriptions')
            ->helpText('Subscription counts by status')
            ->label('status')
            ->value(function () {
                return [
                    [Subscription::where('status', SubscriptionStatus::Active)->count(), ['active']],
                    [Subscription::where('status', SubscriptionStatus::Trialing)->count(), ['trialing']],
                    [Subscription::where('status', SubscriptionStatus::PastDue)->count(), ['past_due']],
                ];
            });
    }
}
