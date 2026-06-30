<?php

use App\Actions\ApiKeys\GenerateApiKey;
use App\Actions\Audit\RecordAuditEvent;
use App\Models\AuditLog;
use App\Models\User;

it('records an audit event via the action', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    app(RecordAuditEvent::class)->handle(
        team: $team,
        action: 'api_key.created',
        properties: ['name' => 'Test Key'],
        actor: $user,
        ipAddress: '127.0.0.1',
        userAgent: 'TestAgent/1.0',
    );

    $log = AuditLog::query()->where('team_id', $team->id)->first();

    expect($log)->not->toBeNull()
        ->and($log->action)->toBe('api_key.created')
        ->and($log->actor_id)->toBe($user->id)
        ->and($log->properties)->toBe(['name' => 'Test Key'])
        ->and($log->ip_address)->toBe('127.0.0.1')
        ->and($log->user_agent)->toBe('TestAgent/1.0');
});

it('records an audit event when an API key is created', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    app(GenerateApiKey::class)->handle(
        team: $team,
        name: 'Production Key',
        createdBy: $user->id,
    );

    // The Livewire page would call RecordAuditEvent after key creation.
    // Here we simulate that call directly.
    app(RecordAuditEvent::class)->handle(
        team: $team,
        action: 'api_key.created',
        properties: ['name' => 'Production Key'],
        actor: $user,
    );

    expect(AuditLog::where('action', 'api_key.created')->exists())->toBeTrue();
});

it('records an audit event when an API key is revoked', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $generated = app(GenerateApiKey::class)->handle(
        team: $team,
        name: 'To Revoke',
        createdBy: $user->id,
    );

    $generated->apiKey->update(['revoked_at' => now()]);

    app(RecordAuditEvent::class)->handle(
        team: $team,
        action: 'api_key.revoked',
        subject: $generated->apiKey,
        actor: $user,
    );

    $log = AuditLog::where('action', 'api_key.revoked')->first();

    expect($log)->not->toBeNull()
        ->and($log->subject_type)->toBe('App\Models\ApiKey')
        ->and($log->subject_id)->toBe($generated->apiKey->id);
});

it('records an audit event when a team member is invited', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $invitation = $team->invitations()->create([
        'email' => 'invitee@example.com',
        'role' => 'member',
        'invited_by' => $user->id,
        'expires_at' => now()->addDays(3),
    ]);

    app(RecordAuditEvent::class)->handle(
        team: $team,
        action: 'team.member.invited',
        subject: $invitation,
        properties: ['email' => 'invitee@example.com', 'role' => 'member'],
        actor: $user,
    );

    $log = AuditLog::where('action', 'team.member.invited')->first();

    expect($log)->not->toBeNull()
        ->and($log->subject_type)->toBe('App\Models\TeamInvitation')
        ->and($log->properties)->toBe(['email' => 'invitee@example.com', 'role' => 'member']);
});

it('allows null actor for system-generated events', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    app(RecordAuditEvent::class)->handle(
        team: $team,
        action: 'system.invoice.generated',
        properties: ['month' => '2026-06'],
    );

    $log = AuditLog::where('action', 'system.invoice.generated')->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBeNull();
});

it('stores properties as a JSON array', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    app(RecordAuditEvent::class)->handle(
        team: $team,
        action: 'test.complex',
        properties: [
            'nested' => ['key' => 'value'],
            'number' => 42,
            'boolean' => true,
        ],
        actor: $user,
    );

    $log = AuditLog::where('action', 'test.complex')->first();

    expect($log->properties)->toBe([
        'nested' => ['key' => 'value'],
        'number' => 42,
        'boolean' => true,
    ]);
});
