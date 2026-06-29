<?php

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('creates a stripe checkout session for an authenticated team member', function () {
    config()->set('services.stripe.secret', 'sk_test_123');

    $user = User::factory()->create();
    $team = $user->currentTeam;

    Http::fake([
        'https://api.stripe.com/v1/checkout/sessions' => Http::response([
            'id' => 'cs_test_controller_1',
            'url' => 'https://checkout.stripe.com/pay/cs_test_controller_1',
        ], 200),
    ]);

    $response = $this->actingAs($user)
        ->postJson("/{$team->slug}/billing/wallet/recharge", [
            'amount_cents' => 50_00,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('session_id', 'cs_test_controller_1');
    $response->assertJsonPath('url', 'https://checkout.stripe.com/pay/cs_test_controller_1');

    Http::assertSent(function (Request $request) use ($team) {
        $data = $request->data();

        return ($data['metadata[wallet_recharge_team_id]'] ?? null) === (string) $team->id
            && ($data['line_items[0][price_data][unit_amount]'] ?? null) === 5000;
    });
});

it('rejects unauthenticated requests to the wallet recharge endpoint', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    // Web-guarded routes redirect to login (302) instead of returning 401.
    $this->postJson("/{$team->slug}/billing/wallet/recharge", [
        'amount_cents' => 50_00,
    ])->assertRedirect();
});

it('rejects a team member who does not belong to the requested team', function () {
    config()->set('services.stripe.secret', 'sk_test_123');

    $owner = User::factory()->create();
    $team = $owner->currentTeam;

    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->postJson("/{$team->slug}/billing/wallet/recharge", [
            'amount_cents' => 50_00,
        ])->assertForbidden();
});

it('validates the amount is at least 100 cents', function () {
    config()->set('services.stripe.secret', 'sk_test_123');

    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->post("/{$team->slug}/billing/wallet/recharge", [
            'amount_cents' => 50,
        ])->assertSessionHasErrors(['amount_cents']);
});

it('accepts a custom currency code', function () {
    config()->set('services.stripe.secret', 'sk_test_123');

    $user = User::factory()->create();
    $team = $user->currentTeam;

    Http::fake([
        'https://api.stripe.com/v1/checkout/sessions' => Http::response([
            'id' => 'cs_test_eur',
            'url' => 'https://checkout.stripe.com/pay/cs_test_eur',
        ], 200),
    ]);

    $this->actingAs($user)
        ->postJson("/{$team->slug}/billing/wallet/recharge", [
            'amount_cents' => 25_00,
            'currency' => 'EUR',
        ])->assertCreated();

    Http::assertSent(function (Request $request) {
        return ($request->data()['line_items[0][price_data][currency]'] ?? null) === 'eur';
    });
});
