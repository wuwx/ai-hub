<?php

use App\Models\TeamWallet;
use App\Models\TeamWalletTransaction;
use App\Models\User;
use Illuminate\Testing\TestResponse;

function postStripeWalletRechargeWebhook(array $eventPayload): TestResponse
{
    $payload = json_encode($eventPayload, JSON_UNESCAPED_UNICODE);
    $timestamp = now()->timestamp;
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_123');

    return test()->call(
        method: 'POST',
        uri: '/api/webhooks/stripe',
        parameters: [],
        cookies: [],
        files: [],
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ],
        content: $payload,
    );
}

it('credits the team wallet when a stripe checkout session completed webhook carries wallet_recharge metadata', function () {
    config()->set('services.stripe.webhook_secret', 'whsec_test_123');

    $user = User::factory()->create();
    $team = $user->currentTeam;

    postStripeWalletRechargeWebhook([
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_recharge_1',
                'amount_total' => 5000, // $50.00
                'currency' => 'usd',
                'metadata' => [
                    'wallet_recharge_team_id' => (string) $team->id,
                ],
            ],
        ],
    ])->assertSuccessful();

    $wallet = TeamWallet::query()->where('team_id', $team->id)->first();
    expect($wallet)->not->toBeNull();
    expect($wallet->balance_cents)->toBe(5000);
    expect($wallet->last_recharged_at)->not->toBeNull();

    $transaction = TeamWalletTransaction::query()
        ->where('team_wallet_id', $wallet->id)
        ->where('type', 'recharge')
        ->first();

    expect($transaction)->not->toBeNull();
    expect($transaction->amount_cents)->toBe(5000);
    expect($transaction->reference_id)->toBe('cs_test_recharge_1');
    expect($transaction->description)->toContain('cs_test_recharge_1');
});

it('skips duplicate wallet recharge webhook events to preserve idempotency', function () {
    config()->set('services.stripe.webhook_secret', 'whsec_test_123');

    $user = User::factory()->create();
    $team = $user->currentTeam;

    $eventPayload = [
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_recharge_dup',
                'amount_total' => 2000,
                'currency' => 'usd',
                'metadata' => [
                    'wallet_recharge_team_id' => (string) $team->id,
                ],
            ],
        ],
    ];

    // First delivery — should credit the wallet.
    postStripeWalletRechargeWebhook($eventPayload)->assertSuccessful();

    // Second delivery of the same event — must not double-credit.
    postStripeWalletRechargeWebhook($eventPayload)->assertSuccessful();

    $wallet = TeamWallet::query()->where('team_id', $team->id)->first();
    expect($wallet->balance_cents)->toBe(2000);

    $rechargeCount = TeamWalletTransaction::query()
        ->where('team_wallet_id', $wallet->id)
        ->where('type', 'recharge')
        ->count();
    expect($rechargeCount)->toBe(1);
});

it('does not credit the wallet when the stripe event has no wallet_recharge metadata', function () {
    config()->set('services.stripe.webhook_secret', 'whsec_test_123');

    $user = User::factory()->create();
    $team = $user->currentTeam;

    // Plain invoice-payment event — should NOT trigger wallet recharge.
    postStripeWalletRechargeWebhook([
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_invoice_only',
                'amount_total' => 5000,
                'currency' => 'usd',
                'metadata' => [
                    'invoice_number' => 'INV-202606-T000010',
                ],
            ],
        ],
    ])->assertSuccessful();

    $wallet = TeamWallet::query()->where('team_id', $team->id)->first();
    expect($wallet)->toBeNull();
});

it('ignores wallet recharge webhook for a non-existent team', function () {
    config()->set('services.stripe.webhook_secret', 'whsec_test_123');

    postStripeWalletRechargeWebhook([
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_recharge_missing_team',
                'amount_total' => 1000,
                'currency' => 'usd',
                'metadata' => [
                    'wallet_recharge_team_id' => '999999',
                ],
            ],
        ],
    ])->assertSuccessful();

    expect(TeamWalletTransaction::query()->where('reference_id', 'cs_test_recharge_missing_team')->exists())->toBeFalse();
});
