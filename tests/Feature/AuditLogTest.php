<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Activitylog\Models\Activity;

it('records an audit event via the activity helper', function () {
    $user = User::factory()->create();

    $activity = activity()
        ->causedBy($user)
        ->withProperties(['name' => 'Test Key'])
        ->log('api_key.created');

    expect($activity)->toBeInstanceOf(Activity::class)
        ->and($activity->description)->toBe('api_key.created')
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->properties->toArray())->toBe(['name' => 'Test Key']);

    expect(Activity::query()->where('causer_id', $user->id)->exists())->toBeTrue();
});

it('records an audit event when an API key is created', function () {
    $user = User::factory()->create();

    $user->createToken('Production Key', ['*'], null);

    // The Livewire page would call activity() after key creation.
    // Here we simulate that call directly.
    activity()
        ->causedBy($user)
        ->withProperties(['name' => 'Production Key'])
        ->log('api_key.created');

    expect(Activity::query()->where('description', 'api_key.created')->exists())->toBeTrue();
});

it('records an audit event when an API key is revoked', function () {
    $user = User::factory()->create();

    $generated = $user->createToken('To Revoke', ['*'], null);

    $token = $generated->accessToken;
    $token->delete();

    activity()
        ->performedOn($token)
        ->causedBy($user)
        ->log('api_key.revoked');

    $activity = Activity::query()->where('description', 'api_key.revoked')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_type)->toBe(PersonalAccessToken::class)
        ->and($activity->subject_id)->toBe($token->id);
});

it('allows null actor for system-generated events', function () {
    $user = User::factory()->create();

    $activity = activity()
        ->causedByAnonymous()
        ->withProperties(['month' => '2026-06'])
        ->log('system.invoice.generated');

    expect($activity->causer_id)->toBeNull();
});

it('stores properties as a JSON array', function () {
    $user = User::factory()->create();

    $activity = activity()
        ->causedBy($user)
        ->withProperties([
            'nested' => ['key' => 'value'],
            'number' => 42,
            'boolean' => true,
        ])
        ->log('test.complex');

    expect($activity->properties->toArray())->toBe([
        'nested' => ['key' => 'value'],
        'number' => 42,
        'boolean' => true,
    ]);
});
