<?php

use App\Models\BillingInvoice;
use App\Models\TeamBillingSubscription;
use App\Models\TeamQuotaPolicy;
use App\Models\User;

it('marks invoice as paid when stripe checkout completed webhook is valid', function () {
    config()->set('services.stripe.webhook_secret', 'whsec_test_123');
    config()->set('services.stripe.webhook_tolerance_seconds', 300);

    $user = User::factory()->create();
    $team = $user->currentTeam;

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

    $payloadArray = [
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_live_paid_1',
                'metadata' => [
                    'invoice_number' => $invoice->invoice_number,
                ],
            ],
        ],
    ];

    $payload = json_encode($payloadArray, JSON_UNESCAPED_UNICODE);
    $timestamp = now()->timestamp;
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_123');

    $response = $this->call(
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

    $response->assertSuccessful();

    $invoice->refresh();

    expect($invoice->status)->toBe('paid');
    expect($invoice->payment_provider)->toBe('stripe');
    expect($invoice->payment_reference)->toBe('cs_live_paid_1');
    expect($invoice->paid_at)->not->toBeNull();
});

it('rejects stripe webhook request with invalid signature', function () {
    config()->set('services.stripe.webhook_secret', 'whsec_test_123');

    $payload = json_encode([
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_invalid']],
    ], JSON_UNESCAPED_UNICODE);

    $response = $this->call(
        method: 'POST',
        uri: '/api/webhooks/stripe',
        parameters: [],
        cookies: [],
        files: [],
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.now()->timestamp.',v1=invalid',
        ],
        content: $payload,
    );

    $response->assertStatus(400);
});

it('syncs team subscription and applies plan quota limits from stripe webhook', function () {
    config()->set('services.stripe.webhook_secret', 'whsec_test_123');
    config()->set('services.billing.free_plan_code', 'free');
    config()->set('services.billing.plans.pro', [
        'daily_token_limit' => 123456,
        'weekly_token_limit' => 654321,
        'monthly_token_limit' => 987654,
    ]);

    $user = User::factory()->create();
    $team = $user->currentTeam;

    $payloadArray = [
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'id' => 'sub_123',
                'customer' => 'cus_123',
                'status' => 'active',
                'current_period_start' => now()->subDay()->timestamp,
                'current_period_end' => now()->addMonth()->timestamp,
                'cancel_at_period_end' => false,
                'metadata' => [
                    'team_id' => (string) $team->id,
                    'plan_code' => 'pro',
                ],
            ],
        ],
    ];

    $payload = json_encode($payloadArray, JSON_UNESCAPED_UNICODE);
    $timestamp = now()->timestamp;
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_123');

    $response = $this->call(
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

    $response->assertSuccessful();

    $subscription = TeamBillingSubscription::query()->where('team_id', $team->id)->first();

    expect($subscription)->not->toBeNull();
    expect($subscription->plan_code)->toBe('pro');
    expect($subscription->status)->toBe('active');
    expect($subscription->stripe_subscription_id)->toBe('sub_123');

    $activePolicy = TeamQuotaPolicy::query()
        ->where('team_id', $team->id)
        ->where('is_active', true)
        ->latest('id')
        ->first();

    expect($activePolicy)->not->toBeNull();
    expect($activePolicy->daily_token_limit)->toBe(123456);
    expect($activePolicy->weekly_token_limit)->toBe(654321);
    expect($activePolicy->monthly_token_limit)->toBe(987654);
});

it('downgrades subscription quota to free when stripe status is past_due', function () {
    config()->set('services.stripe.webhook_secret', 'whsec_test_123');
    config()->set('services.billing.free_plan_code', 'free');
    config()->set('services.billing.plans.free', [
        'daily_token_limit' => 20000,
        'weekly_token_limit' => 120000,
        'monthly_token_limit' => 500000,
    ]);
    config()->set('services.billing.plans.pro', [
        'daily_token_limit' => 123456,
        'weekly_token_limit' => 654321,
        'monthly_token_limit' => 987654,
    ]);

    $user = User::factory()->create();
    $team = $user->currentTeam;

    $payloadArray = [
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'id' => 'sub_past_due_001',
                'customer' => 'cus_past_due_001',
                'status' => 'past_due',
                'metadata' => [
                    'team_id' => (string) $team->id,
                    'plan_code' => 'pro',
                ],
            ],
        ],
    ];

    $payload = json_encode($payloadArray, JSON_UNESCAPED_UNICODE);
    $timestamp = now()->timestamp;
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_123');

    $response = $this->call(
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

    $response->assertSuccessful();

    $subscription = TeamBillingSubscription::query()->where('team_id', $team->id)->first();

    expect($subscription)->not->toBeNull();
    expect($subscription->plan_code)->toBe('pro');
    expect($subscription->status)->toBe('past_due');

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
    config()->set('services.stripe.webhook_secret', 'whsec_test_123');

    $user = User::factory()->create();
    $team = $user->currentTeam;

    $invoice = BillingInvoice::create([
        'team_id' => $team->id,
        'invoice_number' => 'INV-202606-T000001',
        'billing_month' => '2026-06-01',
        'currency' => 'USD',
        'status' => 'paid',
        'payment_provider' => 'stripe',
        'payment_reference' => 'pi_paid_123',
        'subtotal_cents' => 500,
        'tax_cents' => 0,
        'total_cents' => 500,
        'issued_at' => now(),
        'paid_at' => now(),
    ]);

    $payloadArray = [
        'type' => 'charge.refunded',
        'data' => [
            'object' => [
                'id' => 'ch_refunded_123',
                'payment_intent' => 'pi_paid_123',
            ],
        ],
    ];

    $payload = json_encode($payloadArray, JSON_UNESCAPED_UNICODE);
    $timestamp = now()->timestamp;
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_123');

    $response = $this->call(
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

    $response->assertSuccessful();

    $invoice->refresh();

    expect($invoice->status)->toBe('void');
    expect((string) $invoice->notes)->toContain('Refund event ch_refunded_123');
});

it('keeps invoice paid when stripe refund event is partial', function () {
    config()->set('services.stripe.webhook_secret', 'whsec_test_123');

    $user = User::factory()->create();
    $team = $user->currentTeam;

    $invoice = BillingInvoice::create([
        'team_id' => $team->id,
        'invoice_number' => 'INV-202606-T000003',
        'billing_month' => '2026-06-01',
        'currency' => 'USD',
        'status' => 'paid',
        'payment_provider' => 'stripe',
        'payment_reference' => 'pi_paid_456',
        'subtotal_cents' => 900,
        'tax_cents' => 0,
        'total_cents' => 900,
        'issued_at' => now(),
        'paid_at' => now(),
    ]);

    $payloadArray = [
        'type' => 'charge.refund.updated',
        'data' => [
            'object' => [
                'id' => 're_partial_123',
                'payment_intent' => 'pi_paid_456',
                'amount' => 900,
                'amount_refunded' => 200,
            ],
        ],
    ];

    $payload = json_encode($payloadArray, JSON_UNESCAPED_UNICODE);
    $timestamp = now()->timestamp;
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_123');

    $response = $this->call(
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

    $response->assertSuccessful();

    $invoice->refresh();

    expect($invoice->status)->toBe('paid');
    expect((string) $invoice->notes)->toContain('re_partial_123');
});
