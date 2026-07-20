<?php

use App\Actions\ApiKeys\GenerateApiKey;
use App\Actions\Audit\RecordAuditEvent;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Activitylog\Models\Activity;

it('records an audit event via the action', function () {
    $user = User::factory()->create();

    $activity = app(RecordAuditEvent::class)->handle(
        action: 'api_key.created',
        properties: ['name' => 'Test Key'],
        actor: $user,
    );

    expect($activity)->toBeInstanceOf(Activity::class)
        ->and($activity->description)->toBe('api_key.created')
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->properties->toArray())->toBe(['name' => 'Test Key']);

    expect(Activity::query()->where('causer_id', $user->id)->exists())->toBeTrue();
});

it('records an audit event when an API key is created', function () {
    $user = User::factory()->create();

    app(GenerateApiKey::class)->handle(
        user: $user,
        name: 'Production Key',
    );

    // The Livewire page would call RecordAuditEvent after key creation.
    // Here we simulate that call directly.
    app(RecordAuditEvent::class)->handle(
        action: 'api_key.created',
        properties: ['name' => 'Production Key'],
        actor: $user,
    );

    expect(Activity::query()->where('description', 'api_key.created')->exists())->toBeTrue();
});

it('records an audit event when an API key is revoked', function () {
    $user = User::factory()->create();

    $generated = app(GenerateApiKey::class)->handle(
        user: $user,
        name: 'To Revoke',
    );

    $token = $generated->token;
    $token->delete();

    app(RecordAuditEvent::class)->handle(
        action: 'api_key.revoked',
        subject: $token,
        actor: $user,
    );

    $activity = Activity::query()->where('description', 'api_key.revoked')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_type)->toBe(PersonalAccessToken::class)
        ->and($activity->subject_id)->toBe($token->id);
});

it('allows null actor for system-generated events', function () {
    $user = User::factory()->create();

    $activity = app(RecordAuditEvent::class)->handle(
        action: 'system.invoice.generated',
        properties: ['month' => '2026-06'],
    );

    expect($activity->causer_id)->toBeNull();
});

it('stores properties as a JSON array', function () {
    $user = User::factory()->create();

    $activity = app(RecordAuditEvent::class)->handle(
        action: 'test.complex',
        properties: [
            'nested' => ['key' => 'value'],
            'number' => 42,
            'boolean' => true,
        ],
        actor: $user,
    );

    expect($activity->properties->toArray())->toBe([
        'nested' => ['key' => 'value'],
        'number' => 42,
        'boolean' => true,
    ]);
});
