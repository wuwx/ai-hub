<?php

use App\Actions\Usage\GetTeamUsageSnapshot;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\TeamQuotaPolicy;
use App\Models\UsageLedger;
use App\Models\User;
use Carbon\CarbonImmutable;

it('returns zeroed snapshot for a team with no usage', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $at = CarbonImmutable::create(2026, 6, 15, 10, 0, 0);

    $snapshot = app(GetTeamUsageSnapshot::class)->handle($team, $at);

    expect($snapshot)->toHaveKeys([
        'today_tokens', 'today_requests', 'month_tokens', 'month_requests',
        'month_errors', 'month_error_rate', 'daily_limit', 'monthly_limit',
        'daily_remaining', 'monthly_remaining', 'top_models', 'requests_chart',
    ])
        ->and($snapshot['today_tokens'])->toBe(0)
        ->and($snapshot['today_requests'])->toBe(0)
        ->and($snapshot['month_tokens'])->toBe(0)
        ->and($snapshot['month_requests'])->toBe(0)
        ->and($snapshot['month_errors'])->toBe(0)
        ->and($snapshot['month_error_rate'])->toBe(0.0)
        ->and($snapshot['daily_limit'])->toBeNull()
        ->and($snapshot['monthly_limit'])->toBeNull()
        ->and($snapshot['daily_remaining'])->toBeNull()
        ->and($snapshot['monthly_remaining'])->toBeNull()
        ->and($snapshot['top_models'])->toBe([])
        ->and($snapshot['requests_chart']['labels'])->toHaveCount(14)
        ->and($snapshot['requests_chart']['values'])->toHaveCount(14);
});

it('aggregates today and month usage from ledgers', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $at = CarbonImmutable::create(2026, 6, 15, 10, 0, 0);

    TeamQuotaPolicy::create([
        'team_id' => $team->id,
        'daily_token_limit' => 5000,
        'monthly_token_limit' => 50000,
        'effective_from' => $at->subDay(),
        'is_active' => true,
    ]);

    UsageLedger::create([
        'team_id' => $team->id,
        'bucket_date' => $at->toDateString(),
        'bucket_type' => 'day',
        'token_total' => 1200,
        'token_input' => 800,
        'token_output' => 400,
        'request_count' => 10,
        'error_count' => 1,
    ]);

    UsageLedger::create([
        'team_id' => $team->id,
        'bucket_date' => $at->startOfMonth()->toDateString(),
        'bucket_type' => 'month',
        'token_total' => 9000,
        'token_input' => 6000,
        'token_output' => 3000,
        'request_count' => 60,
        'error_count' => 6,
    ]);

    $snapshot = app(GetTeamUsageSnapshot::class)->handle($team, $at);

    expect($snapshot['today_tokens'])->toBe(1200)
        ->and($snapshot['today_requests'])->toBe(10)
        ->and($snapshot['month_tokens'])->toBe(9000)
        ->and($snapshot['month_requests'])->toBe(60)
        ->and($snapshot['month_errors'])->toBe(6)
        ->and($snapshot['month_error_rate'])->toBe(10.0)
        ->and($snapshot['daily_limit'])->toBe(5000)
        ->and($snapshot['monthly_limit'])->toBe(50000)
        ->and($snapshot['daily_remaining'])->toBe(3800)
        ->and($snapshot['monthly_remaining'])->toBe(41000);
});

it('clamps remaining quota to zero when over the limit', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $at = CarbonImmutable::create(2026, 6, 15, 10, 0, 0);

    TeamQuotaPolicy::create([
        'team_id' => $team->id,
        'daily_token_limit' => 100,
        'monthly_token_limit' => 200,
        'effective_from' => $at->subDay(),
        'is_active' => true,
    ]);

    UsageLedger::create([
        'team_id' => $team->id,
        'bucket_date' => $at->toDateString(),
        'bucket_type' => 'day',
        'token_total' => 500,
        'token_input' => 300,
        'token_output' => 200,
        'request_count' => 1,
        'error_count' => 0,
    ]);

    UsageLedger::create([
        'team_id' => $team->id,
        'bucket_date' => $at->startOfMonth()->toDateString(),
        'bucket_type' => 'month',
        'token_total' => 999,
        'token_input' => 500,
        'token_output' => 499,
        'request_count' => 5,
        'error_count' => 0,
    ]);

    $snapshot = app(GetTeamUsageSnapshot::class)->handle($team, $at);

    expect($snapshot['daily_remaining'])->toBe(0)
        ->and($snapshot['monthly_remaining'])->toBe(0)
        ->and($snapshot['month_error_rate'])->toBe(0.0);
});

it('returns top models ranked by token usage for the current month', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $at = CarbonImmutable::create(2026, 6, 15, 10, 0, 0);

    $provider = LlmProvider::create([
        'name' => 'Snapshot Provider',
        'slug' => 'snapshot-provider',
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://api.example.com',
        'auth_mode' => 'bearer',
        'is_active' => true,
    ]);

    $firstModel = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'Hot Model',
        'external_model_id' => 'hot-model',
        'is_active' => true,
    ]);

    $secondModel = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'Cold Model',
        'external_model_id' => 'cold-model',
        'is_active' => true,
    ]);

    UsageLedger::create([
        'team_id' => $team->id,
        'llm_model_id' => $firstModel->id,
        'bucket_date' => $at->startOfMonth()->toDateString(),
        'bucket_type' => 'month',
        'token_total' => 5000,
        'token_input' => 3000,
        'token_output' => 2000,
        'request_count' => 10,
        'error_count' => 0,
    ]);

    UsageLedger::create([
        'team_id' => $team->id,
        'llm_model_id' => $secondModel->id,
        'bucket_date' => $at->startOfMonth()->toDateString(),
        'bucket_type' => 'month',
        'token_total' => 1500,
        'token_input' => 1000,
        'token_output' => 500,
        'request_count' => 3,
        'error_count' => 0,
    ]);

    $snapshot = app(GetTeamUsageSnapshot::class)->handle($team, $at);

    expect($snapshot['top_models'])->toHaveCount(2)
        ->and($snapshot['top_models'][0])->toBe(['name' => 'Hot Model', 'tokens' => 5000])
        ->and($snapshot['top_models'][1])->toBe(['name' => 'Cold Model', 'tokens' => 1500]);
});

it('builds a 14-day requests chart with zero-filled days for gaps', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $at = CarbonImmutable::create(2026, 6, 15, 10, 0, 0);

    // Seed only one day within the 14-day window (3 days ago).
    UsageLedger::create([
        'team_id' => $team->id,
        'bucket_date' => $at->subDays(3)->toDateString(),
        'bucket_type' => 'day',
        'token_total' => 100,
        'token_input' => 60,
        'token_output' => 40,
        'request_count' => 7,
        'error_count' => 0,
    ]);

    $snapshot = app(GetTeamUsageSnapshot::class)->handle($team, $at);

    $values = $snapshot['requests_chart']['values'];
    expect($values)->toHaveCount(14);

    // The seeded day sits at index 14 - 3 - 1 = 10 in the window (start = day-13).
    expect($values[10])->toBe(7)
        ->and($values[0])->toBe(0)
        ->and($values[13])->toBe(0);
});
