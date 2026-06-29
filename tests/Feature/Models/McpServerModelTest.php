<?php

use App\Models\McpServer;
use Carbon\CarbonInterface;

it('casts headers array is_active boolean and last_health_checked_at datetime', function () {
    $checkedAt = now();
    $server = McpServer::create([
        'name' => 'Documents MCP',
        'endpoint' => 'https://mcp.example.com/sse',
        'transport' => 'sse',
        'auth_mode' => 'bearer',
        'secret_ref' => 'mcp_documents_token',
        'headers' => ['X-Custom' => 'value'],
        'is_active' => true,
        'last_health_status' => 'healthy',
        'last_health_checked_at' => $checkedAt,
    ]);

    $fresh = $server->fresh();

    expect($fresh->headers)->toBe(['X-Custom' => 'value'])
        ->and($fresh->is_active)->toBeTrue()
        ->and($fresh->last_health_checked_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($fresh->last_health_checked_at->toDateTimeString())->toBe($checkedAt->toDateTimeString());
});

it('allows null headers and inactive status', function () {
    $server = McpServer::create([
        'name' => 'Minimal MCP',
        'endpoint' => 'https://mcp.example.com/sse',
        'transport' => 'stdio',
        'auth_mode' => 'none',
        'is_active' => false,
    ]);

    expect($server->is_active)->toBeFalse();
});

it('is fillable via mass assignment with all documented fields', function () {
    $server = McpServer::create([
        'name' => 'Tools MCP',
        'endpoint' => 'https://tools.example.com',
        'transport' => 'http',
        'auth_mode' => 'header',
        'secret_ref' => 'tools_secret',
        'headers' => ['Authorization' => 'Bearer xyz'],
        'is_active' => true,
        'last_health_status' => 'unhealthy',
        'last_health_checked_at' => now()->subHour(),
    ]);

    expect($server->exists)->toBeTrue()
        ->and($server->name)->toBe('Tools MCP')
        ->and($server->last_health_status)->toBe('unhealthy');
});
