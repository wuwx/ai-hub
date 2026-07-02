<?php

namespace App\Actions\Teams;

use App\Actions\Billing\SyncTeamQuotaFromSubscription;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTeam
{
    /**
     * Create a new team and add the user as owner.
     */
    public function __construct(
        private readonly SyncTeamQuotaFromSubscription $syncTeamQuota,
    ) {}

    /**
     * Create a new team and add the user as owner.
     */
    public function handle(
        User $user,
        string $name,
        bool $isPersonal = false,
    ): Team {
        return DB::transaction(function () use ($user, $name, $isPersonal) {
            $team = Team::create([
                'name' => $name,
                'is_personal' => $isPersonal,
            ]);

            $membership = $team->memberships()->create([
                'user_id' => $user->id,
                'role' => TeamRole::Owner,
            ]);

            $user->switchTeam($team);

            // Provision the free-plan quota policy so the team can
            // immediately use the gateway.
            $freePlan = (string) config(
                'services.billing.free_plan_code',
                'free',
            );
            $this->syncTeamQuota->handle(
                team: $team,
                planCode: $freePlan,
                status: 'active',
            );

            return $team;
        });
    }
}
