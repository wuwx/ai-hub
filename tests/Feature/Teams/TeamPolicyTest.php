<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Policies\TeamPolicy;

function attachUserToTeam(User $user, Team $team, TeamRole $role): void
{
    $user->teams()->attach($team, ['role' => $role->value]);
}

it('allows any user to view any team list', function () {
    $user = User::factory()->create();

    expect((new TeamPolicy)->viewAny($user))->toBeTrue();
});

it('allows any user to create teams', function () {
    $user = User::factory()->create();

    expect((new TeamPolicy)->create($user))->toBeTrue();
});

it('allows team members to view their team', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $member = User::factory()->create();
    attachUserToTeam($member, $team, TeamRole::Member);

    expect((new TeamPolicy)->view($owner, $team))->toBeTrue()
        ->and((new TeamPolicy)->view($member, $team))->toBeTrue();
});

it('denies non members from viewing the team', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $stranger = User::factory()->create();

    expect((new TeamPolicy)->view($stranger, $team))->toBeFalse();
});

it('allows owners and admins to update the team', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $admin = User::factory()->create();
    attachUserToTeam($admin, $team, TeamRole::Admin);

    expect((new TeamPolicy)->update($owner, $team))->toBeTrue()
        ->and((new TeamPolicy)->update($admin, $team))->toBeTrue();
});

it('denies regular members from updating the team', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $member = User::factory()->create();
    attachUserToTeam($member, $team, TeamRole::Member);

    expect((new TeamPolicy)->update($member, $team))->toBeFalse();
});

it('allows members to leave non personal teams they do not own', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $member = User::factory()->create();
    attachUserToTeam($member, $team, TeamRole::Member);

    expect((new TeamPolicy)->leave($member, $team))->toBeTrue();
});

it('prevents owners from leaving their own team', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    expect((new TeamPolicy)->leave($owner, $team))->toBeFalse();
});

it('prevents members from leaving personal teams', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $member = User::factory()->create();
    attachUserToTeam($member, $team, TeamRole::Member);

    expect((new TeamPolicy)->leave($member, $team))->toBeFalse();
});

it('allows owners to add, update and remove members', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;

    $policy = new TeamPolicy;

    expect($policy->addMember($owner, $team))->toBeTrue()
        ->and($policy->updateMember($owner, $team))->toBeTrue()
        ->and($policy->removeMember($owner, $team))->toBeTrue()
        ->and($policy->inviteMember($owner, $team))->toBeTrue()
        ->and($policy->cancelInvitation($owner, $team))->toBeTrue();
});

it('denies admins and members from managing direct member roles', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $admin = User::factory()->create();
    attachUserToTeam($admin, $team, TeamRole::Admin);
    $member = User::factory()->create();
    attachUserToTeam($member, $team, TeamRole::Member);

    $policy = new TeamPolicy;

    expect($policy->addMember($admin, $team))->toBeFalse()
        ->and($policy->updateMember($admin, $team))->toBeFalse()
        ->and($policy->removeMember($admin, $team))->toBeFalse()
        ->and($policy->addMember($member, $team))->toBeFalse()
        ->and($policy->updateMember($member, $team))->toBeFalse()
        ->and($policy->removeMember($member, $team))->toBeFalse();
});

it('allows admins to invite and cancel invitations', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $admin = User::factory()->create();
    attachUserToTeam($admin, $team, TeamRole::Admin);

    $policy = new TeamPolicy;

    expect($policy->inviteMember($admin, $team))->toBeTrue()
        ->and($policy->cancelInvitation($admin, $team))->toBeTrue();
});

it('denies regular members from inviting or cancelling invitations', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $member = User::factory()->create();
    attachUserToTeam($member, $team, TeamRole::Member);

    $policy = new TeamPolicy;

    expect($policy->inviteMember($member, $team))->toBeFalse()
        ->and($policy->cancelInvitation($member, $team))->toBeFalse();
});

it('allows owners to delete non personal teams', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    expect((new TeamPolicy)->delete($owner, $team))->toBeTrue();
});

it('prevents deletion of personal teams', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;

    expect((new TeamPolicy)->delete($owner, $team))->toBeFalse();
});

it('prevents admins from deleting teams', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $admin = User::factory()->create();
    attachUserToTeam($admin, $team, TeamRole::Admin);

    expect((new TeamPolicy)->delete($admin, $team))->toBeFalse();
});
