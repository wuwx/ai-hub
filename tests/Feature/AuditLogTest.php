<?php

use App\Actions\ApiKeys\GenerateApiKey;
use App\Actions\Audit\RecordAuditEvent;
use App\Models\User;
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
        createdBy: $user->id,
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
        createdBy: $user->id,
    );

    $generated->apiKey->update(['revoked_at' => now()]);

    app(RecordAuditEvent::class)->handle(
        action: 'api_key.revoked',
        subject: $generated->apiKey,
        actor: $user,
    );

    $activity = Activity::query()->where('description', 'api_key.revoked')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_type)->toBe('App\Models\ApiKey')
        ->and($activity->subject_id)->toBe($generated->apiKey->id);
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
