<?php

namespace App\Console\Commands;

use App\Actions\Billing\GenerateMonthlyTeamInvoice;
use App\Models\Team;
use App\Models\TeamWallet;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:generate-monthly-invoices {--month=} {--team-id=} {--include-prepaid : Generate invoices for pre-paid teams too (reconciliation only)}')]
#[Description('Generate monthly usage-based invoices for post-paid teams. Pre-paid teams are billed in real time via the wallet and are skipped by default.')]
class GenerateMonthlyInvoices extends Command
{
    public function __construct(private readonly GenerateMonthlyTeamInvoice $generateMonthlyTeamInvoice)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $monthOption = (string) $this->option('month');

        if ($monthOption !== '') {
            $targetMonth = CarbonImmutable::createFromFormat('Y-m', $monthOption);

            if (! $targetMonth || $targetMonth->format('Y-m') !== $monthOption) {
                $this->error('Invalid --month format, expected YYYY-MM.');

                return self::FAILURE;
            }

            $targetMonth = $targetMonth->startOfMonth();
        } else {
            $targetMonth = now()->startOfMonth();
        }

        $query = Team::query();

        $teamIdOption = $this->option('team-id');

        if ($teamIdOption !== null && $teamIdOption !== '') {
            $query->where('id', (int) $teamIdOption);
        }

        $teams = $query->get();

        if ($teams->isEmpty()) {
            $this->warn('No teams found for invoice generation.');

            return self::SUCCESS;
        }

        $includePrepaid = (bool) $this->option('include-prepaid');
        $generatedCount = 0;
        $skippedPrepaidCount = 0;

        // Cache wallet lookups to avoid N+1 queries.
        $walletsByTeam = TeamWallet::query()
            ->whereIn('team_id', $teams->pluck('id'))
            ->get()
            ->keyBy('team_id');

        foreach ($teams as $team) {
            $wallet = $walletsByTeam->get($team->id);

            // Pre-paid teams are billed in real time through the wallet. Skip
            // them unless the operator explicitly requests reconciliation
            // invoices (useful for accounting/auditing).
            if ($wallet && $wallet->isPrepaid() && ! $includePrepaid) {
                $skippedPrepaidCount++;

                continue;
            }

            $invoice = $this->generateMonthlyTeamInvoice->handle($team, $targetMonth);

            $generatedCount++;

            $this->line(sprintf(
                'Generated %s (%s %0.2f) for team #%d',
                $invoice->invoice_number,
                $invoice->currency,
                $invoice->total_cents / 100,
                $team->id,
            ));
        }

        $this->info(sprintf('Generated %d invoice(s) for %s.', $generatedCount, $targetMonth->format('Y-m')));

        if ($skippedPrepaidCount > 0) {
            $this->info(sprintf('Skipped %d pre-paid team(s) — billed in real time via wallet. Use --include-prepaid to generate reconciliation invoices.', $skippedPrepaidCount));
        }

        return self::SUCCESS;
    }
}
