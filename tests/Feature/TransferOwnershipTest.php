<?php

use App\Actions\Teams\TransferTeamOwnership;
use App\Enums\TeamRole;
use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->team = Team::factory()->create(['is_personal' => false]);

    $this->team->members()->attach($this->owner, ['role' => TeamRole::Owner->value]);

    $this->member = User::factory()->create();
    $this->team->members()->attach($this->member, ['role' => TeamRole::Member->value]);
});

it('transfers ownership from current owner to a team member', function () {
    app(TransferTeamOwnership::class)->handle($this->team, $this->member);

    $this->team->refresh();

    expect($this->team->owner()->id)->toBe($this->member->id);

    $oldOwnerRole = $this->team->memberships()
        ->where('user_id', $this->owner->id)
        ->first()
        ->role;

    expect($oldOwnerRole)->toBe(TeamRole::Admin);
});

it('throws when transferring to the current owner', function () {
    app(TransferTeamOwnership::class)->handle($this->team, $this->owner);
})->throws(RuntimeException::class, 'Cannot transfer ownership to the current owner.');

it('throws when target user is not a team member', function () {
    $nonMember = User::factory()->create();

    app(TransferTeamOwnership::class)->handle($this->team, $nonMember);
})->throws(RuntimeException::class, 'The target user is not a member of this team.');

it('allows the owner to transfer ownership via the UI', function () {
    $this->owner->switchTeam($this->team);

    Livewire::actingAs($this->owner)
        ->test('pages::teams.edit', ['team' => $this->team])
        ->call('transferOwnership', $this->member->id)
        ->assertHasNoErrors();

    $this->team->refresh();

    expect($this->team->owner()->id)->toBe($this->member->id);
});

it('forbids non-owners from transferring ownership', function () {
    $this->member->switchTeam($this->team);

    Livewire::actingAs($this->member)
        ->test('pages::teams.edit', ['team' => $this->team])
        ->call('transferOwnership', $this->member->id)
        ->assertStatus(403);
});

it('records an audit event when ownership is transferred', function () {
    $this->owner->switchTeam($this->team);

    Livewire::actingAs($this->owner)
        ->test('pages::teams.edit', ['team' => $this->team])
        ->call('transferOwnership', $this->member->id);

    $auditLog = AuditLog::where('action', 'team.ownership_transferred')->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog->properties['new_owner_id'])->toBe($this->member->id);
});
