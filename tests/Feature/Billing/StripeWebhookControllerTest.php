<?php

use App\Models\BillingInvoice;
use App\Models\TeamQuotaPolicy;
use App\Models\TeamWallet;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Laravel\Cashier\Subscription as CashierSubscription;

beforeEach(function () {
    config()->set('cashier.webhook.secret', 'whsec_test_123');
    config()->set('cashier.webhook.tolerance', 300);
});

/**
 * Helper to send a signed webhook payload to the Cashier webhook endpoint.
 */
function sendWebhook(array $payloadArray, string $secret = 'whsec_test_123'): TestResponse
{
    $payload = json_encode($payloadArray, JSON_UNESCAPED_UNICODE);
    $timestamp = now()->timestamp;
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

    return test()->call(
        method: 'POST',
        uri: '/stripe/webhook',
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

it('marks invoice as paid when stripe checkout completed webhook is valid', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->stripe_id = 'cus_test_invoice_1';
    $team->save();

    $invoice = BillingInvoice::create([
        'team_id' => $team->id,
        'invoice_number' => 'INV-202606-T000001',
        'billing_month' => '2026-06-01',
        'currency' => 'USD',
        'status' => 'issued',
        'subtotal_cents' => 500,
        'tax_cents' => 0,
        'total_cents' => 500,
        'issued_at' => now(),
    ]);

    $response = sendWebhook([
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_live_paid_1',
                'metadata' => [
                    'invoice_number' => $invoice->invoice_number,
                ],
            ],
        ],
    ]);

    $response->assertSuccessful();

    $invoice->refresh();

    expect($invoice->status)->toBe('paid');
    expect($invoice->payment_reference)->toBe('cs_live_paid_1');
    expect($invoice->paid_at)->not->toBeNull();
});

it('rejects stripe webhook request with invalid signature', function () {
    $payload = json_encode([
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_invalid']],
    ], JSON_UNESCAPED_UNICODE);

    $response = $this->call(
        method: 'POST',
        uri: '/stripe/webhook',
        parameters: [],
        cookies: [],
        files: [],
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.now()->timestamp.',v1=invalid',
        ],
        content: $payload,
    );

    $response->assertStatus(403);
});

it('syncs team subscription and applies plan quota limits from stripe webhook', function () {
    config()->set('services.billing.free_plan_code', 'free');
    config()->set('services.billing.plans.pro', [
        'stripe_price_id' => 'price_pro_test',
        'daily_token_limit' => 123456,
        'weekly_token_limit' => 654321,
        'monthly_token_limit' => 987654,
    ]);

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->stripe_id = 'cus_123';
    $team->save();

    $response = sendWebhook([
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'id' => 'sub_123',
                'customer' => 'cus_123',
                'status' => 'active',
                'current_period_start' => now()->subDay()->timestamp,
                'current_period_end' => now()->addMonth()->timestamp,
                'cancel_at_period_end' => false,
                'items' => [
                    'data' => [
                        [
                            'id' => 'si_123',
                            'price' => ['id' => 'price_pro_test', 'product' => 'prod_123'],
                            'quantity' => 1,
                        ],
                    ],
                ],
                'metadata' => [
                    'team_id' => (string) $team->id,
                    'plan_code' => 'pro',
                ],
            ],
        ],
    ]);

    $response->assertSuccessful();

    // Cashier should have created a subscription record.
    $subscription = CashierSubscription::query()->where('team_id', $team->id)->first();

    expect($subscription)->not->toBeNull();
    expect($subscription->stripe_id)->toBe('sub_123');
    expect($subscription->stripe_status)->toBe('active');
    expect($subscription->stripe_price)->toBe('price_pro_test');

    $activePolicy = TeamQuotaPolicy::query()
        ->where('team_id', $team->id)
        ->where('is_active', true)
        ->latest('id')
        ->first();

    expect($activePolicy)->not->toBeNull();
    expect($activePolicy->daily_token_limit)->toBe(123456);
    expect($activePolicy->weekly_token_limit)->toBe(654321);
    expect($activePolicy->monthly_token_limit)->toBe(987654);

    // An active subscription must provision a post-paid wallet so the gateway
    // pre-flight balance check admits traffic without requiring a manual top-up.
    $wallet = TeamWallet::query()->where('team_id', $team->id)->first();
    expect($wallet)->not->toBeNull()
        ->and($wallet->is_postpaid)->toBeTrue();
});

it('downgrades subscription quota to free when stripe status is past_due', function () {
    config()->set('services.billing.free_plan_code', 'free');
    config()->set('services.billing.plans.free', [
        'daily_token_limit' => 20000,
        'weekly_token_limit' => 120000,
        'monthly_token_limit' => 500000,
    ]);
    config()->set('services.billing.plans.pro', [
        'stripe_price_id' => 'price_pro_test',
        'daily_token_limit' => 123456,
        'weekly_token_limit' => 654321,
        'monthly_token_limit' => 987654,
    ]);

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->stripe_id = 'cus_past_due_001';
    $team->save();

    $response = sendWebhook([
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'id' => 'sub_past_due_001',
                'customer' => 'cus_past_due_001',
                'status' => 'past_due',
                'items' => [
                    'data' => [
                        [
                            'id' => 'si_past_due',
                            'price' => ['id' => 'price_pro_test', 'product' => 'prod_123'],
                            'quantity' => 1,
                        ],
                    ],
                ],
                'metadata' => [
                    'team_id' => (string) $team->id,
                    'plan_code' => 'pro',
                ],
            ],
        ],
    ]);

    $response->assertSuccessful();

    // Cashier should have created/updated the subscription record.
    $subscription = CashierSubscription::query()->where('team_id', $team->id)->first();

    expect($subscription)->not->toBeNull();
    expect($subscription->stripe_id)->toBe('sub_past_due_001');
    expect($subscription->stripe_status)->toBe('past_due');

    $activePolicy = TeamQuotaPolicy::query()
        ->where('team_id', $team->id)
        ->where('is_active', true)
        ->latest('id')
        ->first();

    expect($activePolicy)->not->toBeNull();
    expect($activePolicy->daily_token_limit)->toBe(20000);
    expect($activePolicy->weekly_token_limit)->toBe(120000);
    expect($activePolicy->monthly_token_limit)->toBe(500000);
});

it('marks invoice as void when a matching stripe refund event is received', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->stripe_id = 'cus_refund_1';
    $team->save();

    $invoice = BillingInvoice::create([
        'team_id' => $team->id,
        'invoice_number' => 'INV-202606-T000001',
        'billing_month' => '2026-06-01',
        'currency' => 'USD',
        'status' => 'paid',
        'payment_reference' => 'pi_paid_123',
        'subtotal_cents' => 500,
        'tax_cents' => 0,
        'total_cents' => 500,
        'issued_at' => now(),
        'paid_at' => now(),
    ]);

    $response = sendWebhook([
        'type' => 'charge.refunded',
        'data' => [
            'object' => [
                'id' => 'ch_refunded_123',
                'payment_intent' => 'pi_paid_123',
            ],
        ],
    ]);

    $response->assertSuccessful();

    $invoice->refresh();

    expect($invoice->status)->toBe('void');
    expect((string) $invoice->notes)->toContain('Refund event ch_refunded_123');
});

it('keeps invoice paid when stripe refund event is partial', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->stripe_id = 'cus_refund_2';
    $team->save();

    $invoice = BillingInvoice::create([
        'team_id' => $team->id,
        'invoice_number' => 'INV-202606-T000003',
        'billing_month' => '2026-06-01',
        'currency' => 'USD',
        'status' => 'paid',
        'payment_reference' => 'pi_paid_456',
        'subtotal_cents' => 900,
        'tax_cents' => 0,
        'total_cents' => 900,
        'issued_at' => now(),
        'paid_at' => now(),
    ]);

    $response = sendWebhook([
        'type' => 'charge.refund.updated',
        'data' => [
            'object' => [
                'id' => 're_partial_123',
                'payment_intent' => 'pi_paid_456',
                'amount' => 900,
                'amount_refunded' => 200,
            ],
        ],
    ]);

    $response->assertSuccessful();

    $invoice->refresh();

    expect($invoice->status)->toBe('paid');
    expect((string) $invoice->notes)->toContain('re_partial_123');
});
