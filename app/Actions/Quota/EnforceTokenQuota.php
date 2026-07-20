<?php

namespace App\Actions\Quota;

use App\Exceptions\QuotaExceededException;
use App\Models\User;
use Carbon\CarbonInterface;

class EnforceTokenQuota
{
    /**
     * Token quota feature slugs mapped to their quota period name.
     *
     * @var array<string, string>
     */
    private const PERIODS = [
        'daily-tokens' => 'daily',
        'weekly-tokens' => 'weekly',
        'monthly-tokens' => 'monthly',
    ];

    public function handle(
        User $user,
        int $requestedTokens,
        ?CarbonInterface $at = null,
    ): void {
        if ($requestedTokens <= 0) {
            return;
        }

        foreach (self::PERIODS as $slug => $period) {
            $this->enforce($user, $slug, $period, $requestedTokens);
        }
    }

    protected function enforce(User $user, string $slug, string $period, int $tokens): void
    {
        if (! $user->hasFeature($slug) || $user->isUnlimitedUsage($slug)) {
            return;
        }

        if ($user->canConsume($slug, $tokens)) {
            return;
        }

        $info = $user->featureInfo($slug);

        throw new QuotaExceededException(
            period: $period,
            limit: (int) $info->limit,
            used: (int) $info->used,
            requested: $tokens,
        );
    }
}
