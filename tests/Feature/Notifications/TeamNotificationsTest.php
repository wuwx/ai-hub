<?php

use App\Enums\TeamRole;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\Teams\QuotaThresholdExceeded;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;

it('renders quota threshold exceeded notification mail and array payload', function () {
    $user = User::factory()->create();

    $notification = new QuotaThresholdExceeded(
        period: 'daily',
        used: 850,
        limit: 1000,
        percentage: 85.0,
        teamName: 'Quota Team',
    );

    $mail = $notification->toMail($user);
    $rendered = (string) $mail->render();

    expect($mail->subject)->toBe('Quota threshold exceeded for Quota Team');
    expect($rendered)->toContain('85% of the daily token quota for Quota Team has been consumed.')
        ->toContain('Used: 850 / 1,000 tokens');

    expect($notification->via($user))->toBe(['mail']);

    expect($notification->toArray($user))->toBe([
        'period' => 'daily',
        'used' => 850,
        'limit' => 1000,
        'percentage' => 85.0,
        'team_name' => 'Quota Team',
    ]);
});

it('renders team invitation notification with login action and array payload', function () {
    $inviter = User::factory()->create(['name' => 'Alice Admin']);
    $invitee = User::factory()->create();

    $team = $inviter->currentTeam;
    $team->update(['name' => 'Invite Team']);

    $invitation = TeamInvitation::create([
        'team_id' => $team->id,
        'email' => $invitee->email,
        'role' => TeamRole::Admin,
        'invited_by' => $inviter->id,
        'expires_at' => now()->addDays(7),
    ]);

    $notification = new TeamInvitationNotification($invitation);

    $mail = $notification->toMail($invitee);
    $rendered = (string) $mail->render();

    expect($mail->subject)->toBe("You've been invited to join Invite Team");
    expect($rendered)->toContain('Alice Admin has invited you to join the Invite Team team.');

    // The action URL should embed the invitation code.
    $expectedUrl = route('login', ['invitation' => $invitation->code]);
    expect($mail->actionUrl)->toBe($expectedUrl);

    expect($notification->via($invitee))->toBe(['mail']);

    expect($notification->toArray($invitee))->toBe([
        'invitation_id' => $invitation->id,
        'team_id' => $team->id,
        'team_name' => 'Invite Team',
        'role' => TeamRole::Admin->value,
    ]);
});
