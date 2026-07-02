<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripePortalController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if(! $user, 403);

        if (! $user->hasStripeId()) {
            return response()->json([
                'error' => [
                    'message' => 'No Stripe customer found. Please subscribe to a plan first.',
                ],
            ], 422);
        }

        $returnUrl = rtrim((string) config('app.url'), '/').'/billing';

        try {
            $url = $user->billingPortalUrl($returnUrl);
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
