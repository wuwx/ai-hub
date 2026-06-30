<?php

namespace App\Jobs;

use App\Actions\Billing\GenerateMonthlyTeamInvoice;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateTeamMonthlyInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public int $teamId,
        public CarbonImmutable $targetMonth,
        public bool $includePrepaid = false,
    ) {
        //
    }

    public function handle(GenerateMonthlyTeamInvoice $generateMonthlyTeamInvoice): void
    {
        $team = Team::find($this->teamId);

        if (! $team) {
            Log::info('billing.invoice.team_not_found', ['team_id' => $this->teamId]);

            return;
        }

        $wallet = $team->wallet;

        if ($wallet && $wallet->isPrepaid() && ! $this->includePrepaid) {
            return;
        }

        $generateMonthlyTeamInvoice->handle($team, $this->targetMonth);
    }
}
