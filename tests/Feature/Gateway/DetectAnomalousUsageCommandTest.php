<?php

use App\Actions\ApiKeys\GenerateApiKey;
use App\Models\ApiKey;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\RequestLog;
use App\Models\TeamQuotaPolicy;
use App\Models\User;
use App\Notifications\Teams\AnomalousUsageDetected;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

function seedRequestLogForApiKey(ApiKey $apiKey, int $teamId, int $statusCode, ?string $errorCode, ?CarbonInterface $at = null): void
{
    $at ??= now();

    $suffix = uniqid('', true);

    $provider = LlmProvider::create([
        'name' => 'Anomaly Provider '.$suffix,
        'slug' => 'anomaly-provider-'.$suffix,
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://api.example.com',
        'auth_mode' => 'bearer',
        'is_active' => true,
    ]);

    $model = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'Anomaly Model '.$suffix,
        'external_model_id' => 'anomaly-model-'.$suffix,
        'is_active' => true,
    ]);

    RequestLog::create([
        'team_id' => $teamId,
        'api_key_id' => $apiKey->id,
        'llm_provider_id' => $provider->id,
        'llm_model_id' => $model->id,
        'protocol' => 'openai',
        'endpoint' => '/v1/chat/completions',
        'http_method' => 'POST',
        'status_code' => $statusCode,
        'error_code' => $errorCode,
        'token_input' => 10,
        'token_output' => 5,
        'token_total' => 15,
        'latency_ms' => 100,
        'trace_id' => 'trace_'.uniqid(),
        'requested_at' => $at,
    ]);
}

it('skips keys whose request count is below the minimum threshold', function () {
    Notification::fake();
    Cache::flush();

    $user = User::factory()->create();
    $team = $user->currentTeam;

    TeamQuotaPolicy::create([
        'team_id' => $team->id,
        'daily_token_limit' => 100000,
        'monthly_token_limit' => 1000000,
        'effective_from' => now()->subDay(),
        'is_active' => true,
    ]);

    $apiKey = app(GenerateApiKey::class)->handle(
        team: $team,
        name: 'Low Volume Key',
        createdBy: $user->id,
    )->apiKey;

    // Only 5 requests, below the default --min-requests=50.
    for ($i = 0; $i < 5; $i++) {
        seedRequestLogForApiKey($apiKey, $team->id, 500, 'upstream_error');
    }

    $this->artisan('gateway:detect-anomalous-usage', ['--min-requests' => 50])
        ->assertSuccessful()
        ->expectsOutputToContain('No API key reached 50 requests');

    Notification::assertNothingSent();
});

it('flags high error rate keys and notifies the team owner', function () {
    Notification::fake();
    Cache::flush();

    $user = User::factory()->create();
    $team = $user->currentTeam;

    TeamQuotaPolicy::create([
        'team_id' => $team->id,
        'daily_token_limit' => 100000,
        'monthly_token_limit' => 1000000,
        'effective_from' => now()->subDay(),
        'is_active' => true,
    ]);

    $apiKey = app(GenerateApiKey::class)->handle(
        team: $team,
        name: 'Failing Key',
        createdBy: $user->id,
    )->apiKey;

    // 60 requests, all 500s -> 100% error rate.
    for ($i = 0; $i < 60; $i++) {
        seedRequestLogForApiKey($apiKey, $team->id, 500, 'upstream_error');
    }

    $this->artisan('gateway:detect-anomalous-usage', [
        '--min-requests' => 50,
        '--error-rate' => 50,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Anomaly scan complete: 1 API key(s) flagged, 0 duplicate(s) suppressed.');

    Notification::assertSentTo($team->owner(), AnomalousUsageDetected::class);
});

it('suppresses duplicate alerts for the same key within the dedupe window', function () {
    Notification::fake();
    Cache::flush();

    $user = User::factory()->create();
    $team = $user->currentTeam;

    TeamQuotaPolicy::create([
        'team_id' => $team->id,
        'daily_token_limit' => 100000,
        'monthly_token_limit' => 1000000,
        'effective_from' => now()->subDay(),
        'is_active' => true,
    ]);

    $apiKey = app(GenerateApiKey::class)->handle(
        team: $team,
        name: 'Repeat Key',
        createdBy: $user->id,
    )->apiKey;

    for ($i = 0; $i < 60; $i++) {
        seedRequestLogForApiKey($apiKey, $team->id, 500, 'upstream_error');
    }

    $this->artisan('gateway:detect-anomalous-usage', ['--min-requests' => 50, '--error-rate' => 50])
        ->assertSuccessful();

    Notification::assertSentTimes(AnomalousUsageDetected::class, 1);

    // Second run should be suppressed by the dedupe cache.
    $this->artisan('gateway:detect-anomalous-usage', ['--min-requests' => 50, '--error-rate' => 50])
        ->assertSuccessful()
        ->expectsOutputToContain('1 duplicate(s) suppressed.');

    Notification::assertSentTimes(AnomalousUsageDetected::class, 1);
});

it('ignores keys whose error rate is below the threshold', function () {
    Notification::fake();
    Cache::flush();

    $user = User::factory()->create();
    $team = $user->currentTeam;

    TeamQuotaPolicy::create([
        'team_id' => $team->id,
        'daily_token_limit' => 100000,
        'monthly_token_limit' => 1000000,
        'effective_from' => now()->subDay(),
        'is_active' => true,
    ]);

    $apiKey = app(GenerateApiKey::class)->handle(
        team: $team,
        name: 'Healthy Key',
        createdBy: $user->id,
    )->apiKey;

    // 60 successful requests — 0% error rate.
    for ($i = 0; $i < 60; $i++) {
        seedRequestLogForApiKey($apiKey, $team->id, 200, null);
    }

    $this->artisan('gateway:detect-anomalous-usage', ['--min-requests' => 50, '--error-rate' => 50])
        ->assertSuccessful()
        ->expectsOutputToContain('0 API key(s) flagged');

    Notification::assertNothingSent();
});

it('renders the anomaly notification mail and array payloads', function () {
    $user = User::factory()->create();

    $notification = new AnomalousUsageDetected(
        teamName: 'Acme Team',
        apiKeyName: 'prod-key',
        requestCount: 120,
        errorCount: 90,
        errorRate: 75.0,
        windowMinutes: 60,
    );

    $mail = $notification->toMail($user);
    $rendered = (string) $mail->render();

    expect($mail->subject)->toBe('Anomalous usage detected for Acme Team');
    expect($rendered)->toContain('prod-key')
        ->toContain('Errors: 90 / 120');

    expect($notification->toArray($user))->toBe([
        'team_name' => 'Acme Team',
        'api_key_name' => 'prod-key',
        'request_count' => 120,
        'error_count' => 90,
        'error_rate' => 75.0,
        'window_minutes' => 60,
    ]);

    expect($notification->via($user))->toBe(['mail']);
});
