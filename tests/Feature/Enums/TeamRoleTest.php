<?php

use App\Enums\TeamPermission;
use App\Enums\TeamRole;

it('produces display labels for each role', function () {
    expect(TeamRole::Owner->label())->toBe('Owner')
        ->and(TeamRole::Admin->label())->toBe('Admin')
        ->and(TeamRole::Member->label())->toBe('Member');
});

it('assigns levels by hierarchy', function () {
    expect(TeamRole::Owner->level())->toBe(3)
        ->and(TeamRole::Admin->level())->toBe(2)
        ->and(TeamRole::Member->level())->toBe(1);
});

it('compares roles using isAtLeast', function () {
    expect(TeamRole::Owner->isAtLeast(TeamRole::Admin))->toBeTrue()
        ->and(TeamRole::Owner->isAtLeast(TeamRole::Owner))->toBeTrue()
        ->and(TeamRole::Admin->isAtLeast(TeamRole::Owner))->toBeFalse()
        ->and(TeamRole::Member->isAtLeast(TeamRole::Admin))->toBeFalse();
});

it('grants all permissions to owner', function () {
    foreach (TeamPermission::cases() as $permission) {
        expect(TeamRole::Owner->hasPermission($permission))->toBeTrue();
    }
});

it('grants admin permissions only for admin scope', function () {
    expect(TeamRole::Admin->hasPermission(TeamPermission::UpdateTeam))->toBeTrue()
        ->and(TeamRole::Admin->hasPermission(TeamPermission::ManageApiKeys))->toBeTrue()
        ->and(TeamRole::Admin->hasPermission(TeamPermission::DeleteTeam))->toBeFalse();
});

it('grants no permissions to members', function () {
    foreach (TeamPermission::cases() as $permission) {
        expect(TeamRole::Member->hasPermission($permission))->toBeFalse();
    }
});

it('excludes owner role from assignable list', function () {
    $assignable = TeamRole::assignable();

    expect($assignable)->toHaveCount(2)
        ->and(array_column($assignable, 'value'))->toBe(['admin', 'member'])
        ->and(array_column($assignable, 'label'))->toBe(['Admin', 'Member']);
});
