<?php

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TransferTeamOwnership
{
    /**
     * Transfer team ownership from the current owner to another team member.
     * The previous owner becomes an Admin.
     */
    public function handle(Team $team, User $newOwner): void
    {
        DB::transaction(function () use ($team, $newOwner) {
            $currentOwner = $team->owner();

            if (! $currentOwner) {
                throw new \RuntimeException('Team has no current owner.');
            }

            if ($currentOwner->id === $newOwner->id) {
                throw new \RuntimeException('Cannot transfer ownership to the current owner.');
            }

            $newOwnerMembership = $team->memberships()
                ->where('user_id', $newOwner->id)
                ->first();

            if (! $newOwnerMembership) {
                throw new \RuntimeException('The target user is not a member of this team.');
            }

            // Demote current owner to Admin
            $team->memberships()
                ->where('user_id', $currentOwner->id)
                ->update(['role' => TeamRole::Admin]);

            // Promote new owner
            $team->memberships()
                ->where('user_id', $newOwner->id)
                ->update(['role' => TeamRole::Owner]);
        });
    }
}
