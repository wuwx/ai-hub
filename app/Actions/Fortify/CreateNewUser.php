<?php

namespace App\Actions\Fortify;

use App\Actions\Billing\RechargeTeamWallet;
use App\Actions\Teams\CreateTeam;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        private CreateTeam $createTeam,
        private RechargeTeamWallet $rechargeTeamWallet,
    ) {
        //
    }

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $team = $this->createTeam->handle($user, $user->name."'s Team", isPersonal: true);

            $this->grantSignupCredit($team);

            return $user;
        });
    }

    /**
     * Grant the configured signup credit to the team's wallet.
     */
    protected function grantSignupCredit($team): void
    {
        $creditCents = (int) config('services.billing.signup_credit_cents', 0);

        if ($creditCents <= 0) {
            return;
        }

        $this->rechargeTeamWallet->handle(
            team: $team,
            amountCents: $creditCents,
            description: 'Signup credit',
            metadata: ['type' => 'signup_bonus'],
        );
    }
}
