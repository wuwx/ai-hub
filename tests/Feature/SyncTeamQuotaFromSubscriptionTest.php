<?php

use App\Actions\Billing\SyncTeamQuotaFromSubscription;
use App\Models\TeamQuotaPolicy;
use App\Models\TeamWallet;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
});

it('creates a quota policy with the correct plan_code', function () {
    $sync = app(SyncTeamQuotaFromSubscription::class);
    $policy = $sync->handle(team: $this->team, planCode: 'pro', status: 'active');

    expect($policy)->toBeInstanceOf(TeamQuotaPolicy::class)
        ->and($policy->plan_code)->toBe('pro')
        ->and($policy->is_active)->toBeTrue();
});

it('creates a wallet for active subscriptions', function () {
    $sync = app(SyncTeamQuotaFromSubscription::class);
    $sync->handle(team: $this->team, planCode: 'pro', status: 'active');

    expect(TeamWallet::where('team_id', $this->team->id)->exists())->toBeTrue();
});

it('does not create a wallet for inactive subscriptions', function () {
    $sync = app(SyncTeamQuotaFromSubscription::class);
    $sync->handle(team: $this->team, planCode: 'pro', status: 'canceled');

    expect(TeamWallet::where('team_id', $this->team->id)->exists())->toBeFalse();
});

it('falls back to free plan when status is not active or trialing', function () {
    $sync = app(SyncTeamQuotaFromSubscription::class);
    $policy = $sync->handle(team: $this->team, planCode: 'pro', status: 'canceled');

    expect($policy->plan_code)->toBe('free');
});

it('does not duplicate policy on repeated calls with same plan', function () {
    $sync = app(SyncTeamQuotaFromSubscription::class);

    $first = $sync->handle(team: $this->team, planCode: 'pro', status: 'active');
    $second = $sync->handle(team: $this->team, planCode: 'pro', status: 'active');

    expect($first->id)->toBe($second->id)
        ->and(TeamQuotaPolicy::where('team_id', $this->team->id)->where('is_active', true)->count())->toBe(1);
});

it('replaces policy when plan changes', function () {
    $sync = app(SyncTeamQuotaFromSubscription::class);

    $sync->handle(team: $this->team, planCode: 'free', status: 'active');
    $sync->handle(team: $this->team, planCode: 'pro', status: 'active');

    $activePolicies = TeamQuotaPolicy::where('team_id', $this->team->id)->where('is_active', true)->get();

    expect($activePolicies)->toHaveCount(1)
        ->and($activePolicies->first()->plan_code)->toBe('pro');
});
