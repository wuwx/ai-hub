<?php

namespace App\Actions\Billing;

use App\Models\LlmModel;

class ResolveModelPricing
{
    /**
     * Resolve the SELL price we charge the customer for a given model.
     *
     * Precedence:
     *   1. Explicit sell_* columns on the model row (the operator-set price).
     *   2. markup_percent applied to cost_* columns.
     *   3. markup_percent applied to legacy `pricing` JSON cost rates.
     *   4. Legacy `pricing` JSON values as-is (treated as sell price).
     *
     * @return array{input_per_1m_usd: float, output_per_1m_usd: float, is_markup: bool}
     */
    public function sellRates(LlmModel $model): array
    {
        $input = (float) $model->sell_input_per_1m_usd;
        $output = (float) $model->sell_output_per_1m_usd;

        if ($input > 0 || $output > 0) {
            return [
                'input_per_1m_usd' => $input,
                'output_per_1m_usd' => $output,
                'is_markup' => false,
            ];
        }

        [$costInput, $costOutput] = $this->costRates($model);
        $markup = max(0, (float) $model->markup_percent);

        if ($markup > 0 && ($costInput > 0 || $costOutput > 0)) {
            return [
                'input_per_1m_usd' => $costInput * (1 + $markup / 100),
                'output_per_1m_usd' => $costOutput * (1 + $markup / 100),
                'is_markup' => true,
            ];
        }

        return [
            'input_per_1m_usd' => $costInput,
            'output_per_1m_usd' => $costOutput,
            'is_markup' => false,
        ];
    }

    /**
     * Resolve the COST price we pay the upstream provider.
     *
     * @return array{0: float, 1: float} [input_per_1m_usd, output_per_1m_usd]
     */
    public function costRates(LlmModel $model): array
    {
        $input = (float) $model->cost_input_per_1m_usd;
        $output = (float) $model->cost_output_per_1m_usd;

        if ($input > 0 || $output > 0) {
            return [$input, $output];
        }

        // Fall back to the legacy pricing JSON, which historically encoded
        // per-1M or per-1K token costs.
        $pricing = is_array($model->pricing) ? $model->pricing : [];

        $input = $this->coercePer1mRate($pricing, ['input_per_1m_tokens', 'prompt_per_1m_tokens'])
            ?? $this->coercePer1kRate($pricing, ['input_per_1k_tokens', 'prompt_per_1k_tokens'])
            ?? 0.0;

        $output = $this->coercePer1mRate($pricing, ['output_per_1m_tokens', 'completion_per_1m_tokens'])
            ?? $this->coercePer1kRate($pricing, ['output_per_1k_tokens', 'completion_per_1k_tokens'])
            ?? 0.0;

        return [$input, $output];
    }

    /**
     * Compute the charge in cents for a token pair at sell rates.
     */
    public function chargeCents(LlmModel $model, int $tokenInput, int $tokenOutput): int
    {
        $rates = $this->sellRates($model);

        $inputUsd = ($tokenInput / 1_000_000) * $rates['input_per_1m_usd'];
        $outputUsd = ($tokenOutput / 1_000_000) * $rates['output_per_1m_usd'];

        return (int) round(($inputUsd + $outputUsd) * 100);
    }

    /**
     * Compute the cost we incur (for margin reporting).
     */
    public function costCents(LlmModel $model, int $tokenInput, int $tokenOutput): int
    {
        [$inputPer1m, $outputPer1m] = $this->costRates($model);

        $inputUsd = ($tokenInput / 1_000_000) * $inputPer1m;
        $outputUsd = ($tokenOutput / 1_000_000) * $outputPer1m;

        return (int) round(($inputUsd + $outputUsd) * 100);
    }

    /**
     * @param  array<string, mixed>  $pricing
     * @param  array<int, string>  $keys
     */
    protected function coercePer1mRate(array $pricing, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (isset($pricing[$key]) && is_numeric($pricing[$key]) && (float) $pricing[$key] >= 0) {
                return (float) $pricing[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $pricing
     * @param  array<int, string>  $keys
     */
    protected function coercePer1kRate(array $pricing, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (isset($pricing[$key]) && is_numeric($pricing[$key]) && (float) $pricing[$key] >= 0) {
                return (float) $pricing[$key] * 1000;
            }
        }

        return null;
    }
}
