<?php

use App\Models\RequestLog;
use App\Models\User;

it('deletes request logs older than the specified retention window', function () {
    $user = User::factory()->create();

    $oldLog = RequestLog::create([
        'trace_id' => 'trace_old',
        'user_id' => $user->id,
        'protocol' => 'openai',
        'endpoint' => '/v1/chat/completions',
        'http_method' => 'POST',
        'is_streaming' => false,
        'tool_calls_count' => 0,
        'status_code' => 200,
        'token_input' => 10,
        'token_output' => 5,
        'token_total' => 15,
        'latency_ms' => 100,
        'requested_at' => now()->subDays(45),
    ]);

    $recentLog = RequestLog::create([
        'trace_id' => 'trace_recent',
        'user_id' => $user->id,
        'protocol' => 'openai',
        'endpoint' => '/v1/chat/completions',
        'http_method' => 'POST',
        'is_streaming' => false,
        'tool_calls_count' => 0,
        'status_code' => 200,
        'token_input' => 8,
        'token_output' => 4,
        'token_total' => 12,
        'latency_ms' => 80,
        'requested_at' => now()->subDays(5),
    ]);

    $this->artisan('gateway:prune-request-logs --days=30')
        ->assertSuccessful()
        ->expectsOutputToContain('Deleted 1 request logs');

    $this->assertDatabaseMissing('request_logs', ['id' => $oldLog->id]);
    $this->assertDatabaseHas('request_logs', ['id' => $recentLog->id]);
});

it('supports a custom retention window via the days option', function () {
    $user = User::factory()->create();

    $tenDayOldLog = RequestLog::create([
        'trace_id' => 'trace_10',
        'user_id' => $user->id,
        'protocol' => 'openai',
        'endpoint' => '/v1/chat/completions',
        'http_method' => 'POST',
        'is_streaming' => false,
        'tool_calls_count' => 0,
        'status_code' => 200,
        'token_input' => 10,
        'token_output' => 5,
        'token_total' => 15,
        'latency_ms' => 100,
        'requested_at' => now()->subDays(10),
    ]);

    $threeDayOldLog = RequestLog::create([
        'trace_id' => 'trace_3',
        'user_id' => $user->id,
        'protocol' => 'openai',
        'endpoint' => '/v1/chat/completions',
        'http_method' => 'POST',
        'is_streaming' => false,
        'tool_calls_count' => 0,
        'status_code' => 200,
        'token_input' => 8,
        'token_output' => 4,
        'token_total' => 12,
        'latency_ms' => 80,
        'requested_at' => now()->subDays(3),
    ]);

    $this->artisan('gateway:prune-request-logs --days=7')
        ->assertSuccessful();

    $this->assertDatabaseMissing('request_logs', ['id' => $tenDayOldLog->id]);
    $this->assertDatabaseHas('request_logs', ['id' => $threeDayOldLog->id]);
});

it('does not delete anything in dry-run mode', function () {
    $user = User::factory()->create();

    $oldLog = RequestLog::create([
        'trace_id' => 'trace_old',
        'user_id' => $user->id,
        'protocol' => 'openai',
        'endpoint' => '/v1/chat/completions',
        'http_method' => 'POST',
        'is_streaming' => false,
        'tool_calls_count' => 0,
        'status_code' => 200,
        'token_input' => 10,
        'token_output' => 5,
        'token_total' => 15,
        'latency_ms' => 100,
        'requested_at' => now()->subDays(45),
    ]);

    $this->artisan('gateway:prune-request-logs --days=30 --dry-run')
        ->assertSuccessful()
        ->expectsOutputToContain('Dry run: would delete 1 request logs');

    $this->assertDatabaseHas('request_logs', ['id' => $oldLog->id]);
});
