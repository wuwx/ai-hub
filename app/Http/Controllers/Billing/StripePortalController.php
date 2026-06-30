<?php

namespace App\Http\Controllers\Billing;

use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamBillingSubscription;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripePortalController extends Controller
{
    public function store(Request $request, Team $current_team): JsonResponse
    {
        $user = $request->user();

        abort_if(! $user || ! $user->belongsToTeam($current_team), 403);
        abort_if(! $user->hasTeamPermission($current_team, TeamPermission::ManageBilling), 403);

        $subscription = TeamBillingSubscription::where('team_id', $current_team->id)
            ->whereNotNull('stripe_customer_id')
            ->latest()
            ->first();

        if (! $subscription || ! $subscription->stripe_customer_id) {
            return response()->json([
                'error' => [
                    'message' => 'No Stripe customer found. Please subscribe to a plan first.',
                ],
            ], 422);
        }

        $stripeSecret = (string) config('services.stripe.secret', '');

        if ($stripeSecret === '') {
            throw new RuntimeException('Stripe secret key is not configured.');
        }

        $returnUrl = rtrim((string) config('app.url'), '/').'/'.(string) $current_team->slug.'/billing';

        try {
            $response = Http::asForm()
                ->withToken($stripeSecret)
                ->post('https://api.stripe.com/v1/billing_portal/sessions', [
                    'customer' => $subscription->stripe_customer_id,
                    'return_url' => $returnUrl,
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Unable to reach Stripe API.', previous: $exception);
        }

        if ($response->failed()) {
            return response()->json([
                'error' => [
                    'message' => 'Failed to create Stripe portal session.',
                ],
            ], 502);
        }

        $body = $response->json();

        $url = $body['url'] ?? null;

        if (! is_string($url)) {
            return response()->json([
                'error' => [
                    'message' => 'Stripe returned an invalid portal session payload.',
                ],
            ], 502);
        }

        return response()->json([
            'url' => $url,
        ]);
    }
}
