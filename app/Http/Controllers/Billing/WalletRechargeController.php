<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\CreateWalletRechargeSession;
use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletRechargeController extends Controller
{
    public function __construct(private readonly CreateWalletRechargeSession $createWalletRechargeSession)
    {
        //
    }

    /**
     * Create a Stripe Checkout session for topping up the team wallet.
     */
    public function store(Request $request, Team $current_team): JsonResponse
    {
        $data = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:100'], // minimum $1.00
            'currency' => ['sometimes', 'string', 'size:3'],
        ]);

        $result = $this->createWalletRechargeSession->handle(
            team: $current_team,
            amountCents: (int) $data['amount_cents'],
            currency: strtoupper((string) ($data['currency'] ?? 'USD')),
        );

        return response()->json($result, 201);
    }
}
