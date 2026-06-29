<?php

use App\Models\BillingInvoice;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\RequestLog;
use App\Models\TeamWallet;
use App\Models\User;
use Carbon\CarbonImmutable;

function seedRequestLogForTeam(int $teamId, CarbonImmutable $month): array
{
    $provider = LlmProvider::create([
        'name' => 'Provider '.$teamId.' '.$month->format('Ym'),
        'slug' => 'provider-'.$teamId.'-'.$month->format('Ym'),
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://command.example.com',
        'auth_mode' => 'none',
        'is_active' => true,
    ]);

    $model = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'Model '.$teamId,
        'external_model_id' => 'model-'.$teamId.'-'.$month->format('Ym'),
        'pricing' => [
            'input_per_1k_tokens' => 0.02,
            'output_per_1k_tokens' => 0.01,
        ],
        'is_active' => true,
    ]);

    RequestLog::create([
        'team_id' => $teamId,
        'llm_provider_id' => $provider->id,
        'llm_model_id' => $model->id,
        'protocol' => 'openai',
        'endpoint' => '/v1/chat/completions',
        'http_method' => 'POST',
        'is_streaming' => false,
        'status_code' => 200,
        'token_input' => 1000,
        'token_output' => 2000,
        'token_total' => 3000,
        'requested_at' => $month->copy()->addDays(8),
    ]);

    return [$provider, $model];
}

it('generates invoices with billing command for the selected month', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $month = CarbonImmutable::create(2026, 6, 1, 0, 0, 0);

    seedRequestLogForTeam($team->id, $month);

    $this->artisan('billing:generate-monthly-invoices', ['--month' => '2026-06'])
        ->assertSuccessful();

    $invoice = BillingInvoice::query()
        ->where('team_id', $team->id)
        ->whereDate('billing_month', '2026-06-01')
        ->first();

    expect($invoice)->not->toBeNull();
    expect($invoice->total_cents)->toBe(4);
    expect($invoice->status)->toBe('issued');
});

it('skips pre-paid teams by default to avoid double billing', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    // Mark the team as pre-paid with a positive balance.
    TeamWallet::create([
        'team_id' => $team->id,
        'balance_cents' => 5000,
        'credit_grant_cents' => 0,
        'currency' => 'USD',
        'is_postpaid' => false,
    ]);

    $month = CarbonImmutable::create(2026, 6, 1, 0, 0, 0);
    seedRequestLogForTeam($team->id, $month);

    $this->artisan('billing:generate-monthly-invoices', ['--month' => '2026-06'])
        ->assertSuccessful()
        ->expectsOutputToContain('Skipped 1 pre-paid team(s)');

    $invoice = BillingInvoice::query()
        ->where('team_id', $team->id)
        ->whereDate('billing_month', '2026-06-01')
        ->first();

    expect($invoice)->toBeNull();
});

it('generates reconciliation invoices for pre-paid teams when --include-prepaid is set', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    TeamWallet::create([
        'team_id' => $team->id,
        'balance_cents' => 5000,
        'credit_grant_cents' => 0,
        'currency' => 'USD',
        'is_postpaid' => false,
    ]);

    $month = CarbonImmutable::create(2026, 6, 1, 0, 0, 0);
    seedRequestLogForTeam($team->id, $month);

    $this->artisan('billing:generate-monthly-invoices', ['--month' => '2026-06', '--include-prepaid' => true])
        ->assertSuccessful();

    $invoice = BillingInvoice::query()
        ->where('team_id', $team->id)
        ->whereDate('billing_month', '2026-06-01')
        ->first();

    expect($invoice)->not->toBeNull();
    expect($invoice->total_cents)->toBe(4);
});
