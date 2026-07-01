<?php

namespace App\Http\Controllers\Billing;

use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripePortalController extends Controller
{
    public function store(Request $request, Team $current_team): JsonResponse
    {
        $user = $request->user();

        abort_if(! $user || ! $user->belongsToTeam($current_team), 403);
        abort_if(! $user->hasTeamPermission($current_team, TeamPermission::ManageBilling), 403);

        if (! $current_team->hasStripeId()) {
            return response()->json([
                'error' => [
                    'message' => 'No Stripe customer found. Please subscribe to a plan first.',
                ],
            ], 422);
        }

        $returnUrl = rtrim((string) config('app.url'), '/').'/'.(string) $current_team->slug.'/billing';

        try {
            $url = $current_team->billingPortalUrl($returnUrl);
        } catch (\Exception $exception) {
            return response()->json([
                'error' => [
                    'message' => 'Failed to create Stripe portal session.',
                ],
            ], 502);
        }

        return response()->json([
            'url' => $url,
        ]);
    }
}
