<?php

namespace App\Console\Commands;

use App\Actions\Billing\GenerateMonthlyTeamInvoice;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:generate-monthly-invoices {--month=} {--team-id=}')]
#[Description('Generate monthly usage-based invoices for teams.')]
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

        $generatedCount = 0;

        foreach ($teams as $team) {
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

        return self::SUCCESS;
    }
}
