<?php

use App\Actions\Billing\CreateWalletRechargeSession;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('creates a stripe checkout session for a wallet top-up', function () {
    config()->set('services.stripe.secret', 'sk_test_123');
    config()->set('services.billing.wallet_recharge_success_url', 'https://app.example.com/billing/wallet/success');
    config()->set('services.billing.wallet_recharge_cancel_url', 'https://app.example.com/billing/wallet/cancel');

    $user = User::factory()->create();
    $team = $user->currentTeam;

    Http::fake([
        'https://api.stripe.com/v1/checkout/sessions' => Http::response([
            'id' => 'cs_test_wallet_1',
            'url' => 'https://checkout.stripe.com/pay/cs_test_wallet_1',
        ], 200),
    ]);

    $result = app(CreateWalletRechargeSession::class)->handle($team, 50_00);

    expect($result['session_id'])->toBe('cs_test_wallet_1');
    expect($result['url'])->toBe('https://checkout.stripe.com/pay/cs_test_wallet_1');

    Http::assertSent(function (Request $request) use ($team) {
        $data = $request->data();

        return str_contains($request->url(), 'checkout/sessions')
            && ($data['metadata[wallet_recharge_team_id]'] ?? null) === (string) $team->id
            && ($data['line_items[0][price_data][unit_amount]'] ?? null) === 5000;
    });
});

it('includes the requested currency in the stripe payload', function () {
    config()->set('services.stripe.secret', 'sk_test_123');

    $user = User::factory()->create();
    $team = $user->currentTeam;

    Http::fake([
        'https://api.stripe.com/v1/checkout/sessions' => Http::response([
            'id' => 'cs_test_2',
            'url' => 'https://checkout.stripe.com/pay/cs_test_2',
        ], 200),
    ]);

    app(CreateWalletRechargeSession::class)->handle($team, 10_00, 'EUR');

    Http::assertSent(function (Request $request) {
        $data = $request->data();

        return ($data['line_items[0][price_data][currency]'] ?? null) === 'eur'
            && ($data['metadata[recharge_currency]'] ?? null) === 'eur';
    });
});

it('throws when the recharge amount is non-positive', function () {
    config()->set('services.stripe.secret', 'sk_test_123');

    $user = User::factory()->create();

    app(CreateWalletRechargeSession::class)->handle($user->currentTeam, 0);
})->throws(RuntimeException::class, 'Recharge amount must be positive.');

it('throws when stripe secret is not configured', function () {
    config()->set('services.stripe.secret', '');

    $user = User::factory()->create();

    app(CreateWalletRechargeSession::class)->handle($user->currentTeam, 10_00);
})->throws(RuntimeException::class, 'Stripe secret key is not configured.');

it('throws when the stripe api rejects the request', function () {
    config()->set('services.stripe.secret', 'sk_test_123');

    $user = User::factory()->create();

    Http::fake([
        'https://api.stripe.com/v1/checkout/sessions' => Http::response(['error' => 'invalid'], 400),
    ]);

    app(CreateWalletRechargeSession::class)->handle($user->currentTeam, 10_00);
})->throws(RuntimeException::class, 'Stripe API rejected wallet recharge session creation.');
