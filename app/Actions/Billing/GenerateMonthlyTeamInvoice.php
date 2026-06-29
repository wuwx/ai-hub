<?php

namespace App\Actions\Billing;

use App\Models\BillingInvoice;
use App\Models\LlmModel;
use App\Models\RequestLog;
use App\Models\Team;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class GenerateMonthlyTeamInvoice
{
    public function handle(Team $team, ?CarbonInterface $month = null): BillingInvoice
    {
        $month ??= now();

        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();
        $currency = strtoupper((string) config('services.billing.currency', 'USD'));
        $dueDays = max(0, (int) config('services.billing.invoice_due_days', 7));

        return DB::transaction(function () use ($team, $monthStart, $monthEnd, $currency, $dueDays): BillingInvoice {
            $invoice = BillingInvoice::query()
                ->where('team_id', $team->id)
                ->whereDate('billing_month', $monthStart->toDateString())
                ->first();

            if (! $invoice) {
                $invoice = new BillingInvoice([
                    'team_id' => $team->id,
                    'billing_month' => $monthStart->toDateString(),
                ]);
            }

            if ($invoice->exists && $invoice->isFinalized()) {
                return $invoice->load('items.llmModel');
            }

            if (! $invoice->exists) {
                $invoice->invoice_number = $this->buildInvoiceNumber($team, $monthStart);
            }

            $invoice->currency = $currency;
            $invoice->status = 'issued';
            $invoice->issued_at ??= now();
            $invoice->due_at = $invoice->due_at ?? $monthEnd->copy()->addDays($dueDays);
            $invoice->paid_at = $invoice->status === 'paid' ? $invoice->paid_at : null;
            $invoice->notes = $invoice->notes;
            $invoice->subtotal_cents = 0;
            $invoice->tax_cents = 0;
            $invoice->total_cents = 0;
            $invoice->save();

            $usageRows = RequestLog::query()
                ->where('team_id', $team->id)
                ->whereBetween('requested_at', [$monthStart, $monthEnd])
                ->selectRaw('llm_model_id, SUM(token_input) as token_input, SUM(token_output) as token_output, SUM(token_total) as token_total, COUNT(*) as requests_count')
                ->groupBy('llm_model_id')
                ->get();

            $models = LlmModel::query()
                ->whereIn('id', $usageRows->pluck('llm_model_id')->filter()->values())
                ->get()
                ->keyBy('id');

            $invoice->items()->delete();

            $subtotalCents = 0;

            foreach ($usageRows as $usageRow) {
                $model = $usageRow->llm_model_id ? $models->get((int) $usageRow->llm_model_id) : null;
                $rates = $this->resolveModelRates($model);

                $lineCostMicros = $this->costMicros(
                    tokenInput: (int) $usageRow->token_input,
                    tokenOutput: (int) $usageRow->token_output,
                    inputRateMicrosPer1k: $rates['input_micros_per_1k'],
                    outputRateMicrosPer1k: $rates['output_micros_per_1k'],
                );

                $lineSubtotalCents = (int) round($lineCostMicros / 10_000);
                $subtotalCents += $lineSubtotalCents;

                $tokenTotal = max(0, (int) $usageRow->token_total);
                $unitMicros = $tokenTotal > 0 ? (int) round($lineCostMicros / $tokenTotal) : 0;

                $invoice->items()->create([
                    'llm_model_id' => $model?->id,
                    'description' => $model
                        ? sprintf('Model %s usage (%s)', $model->name, $model->external_model_id)
                        : 'Unmapped model usage',
                    'token_input' => max(0, (int) $usageRow->token_input),
                    'token_output' => max(0, (int) $usageRow->token_output),
                    'token_total' => $tokenTotal,
                    'unit_amount_micros' => max(0, $unitMicros),
                    'line_subtotal_cents' => max(0, $lineSubtotalCents),
                    'metadata' => [
                        'requests_count' => max(0, (int) $usageRow->requests_count),
                        'input_rate_micros_per_1k' => $rates['input_micros_per_1k'],
                        'output_rate_micros_per_1k' => $rates['output_micros_per_1k'],
                    ],
                ]);
            }

            $invoice->subtotal_cents = $subtotalCents;
            $invoice->tax_cents = 0;
            $invoice->total_cents = $subtotalCents;
            $invoice->save();

            return $invoice->load('items.llmModel');
        });
    }

    protected function buildInvoiceNumber(Team $team, CarbonInterface $monthStart): string
    {
        return sprintf('INV-%s-T%06d', $monthStart->format('Ym'), $team->id);
    }

    /**
     * @return array{input_micros_per_1k: int, output_micros_per_1k: int}
     */
    protected function resolveModelRates(?LlmModel $model): array
    {
        $pricing = is_array($model?->pricing) ? $model->pricing : [];

        $inputRatePer1k = $this->firstNumeric([
            $pricing['input_per_1k_tokens'] ?? null,
            $pricing['prompt_per_1k_tokens'] ?? null,
        ]);

        if ($inputRatePer1k === null) {
            $inputPer1m = $this->firstNumeric([
                $pricing['input_per_1m_tokens'] ?? null,
                $pricing['prompt_per_1m_tokens'] ?? null,
            ]);

            $inputRatePer1k = $inputPer1m !== null ? $inputPer1m / 1000 : 0.0;
        }

        $outputRatePer1k = $this->firstNumeric([
            $pricing['output_per_1k_tokens'] ?? null,
            $pricing['completion_per_1k_tokens'] ?? null,
        ]);

        if ($outputRatePer1k === null) {
            $outputPer1m = $this->firstNumeric([
                $pricing['output_per_1m_tokens'] ?? null,
                $pricing['completion_per_1m_tokens'] ?? null,
            ]);

            $outputRatePer1k = $outputPer1m !== null ? $outputPer1m / 1000 : 0.0;
        }

        return [
            'input_micros_per_1k' => (int) round(max(0, $inputRatePer1k) * 1_000_000),
            'output_micros_per_1k' => (int) round(max(0, $outputRatePer1k) * 1_000_000),
        ];
    }

    /**
     * @param  array<int, mixed>  $candidates
     */
    protected function firstNumeric(array $candidates): ?float
    {
        foreach ($candidates as $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (! is_numeric($value)) {
                continue;
            }

            $rate = (float) $value;

            if ($rate < 0) {
                continue;
            }

            return $rate;
        }

        return null;
    }

    protected function costMicros(int $tokenInput, int $tokenOutput, int $inputRateMicrosPer1k, int $outputRateMicrosPer1k): int
    {
        $inputCost = (int) round((max(0, $tokenInput) * max(0, $inputRateMicrosPer1k)) / 1000);
        $outputCost = (int) round((max(0, $tokenOutput) * max(0, $outputRateMicrosPer1k)) / 1000);

        return $inputCost + $outputCost;
    }
}
