<?php

namespace App\Actions\Billing;

use App\Models\LlmModel;

class ResolveModelPricing
{
    /**
     * Resolve the COST price we pay the upstream provider.
     *
     * @return array{0: float, 1: float} [input_per_1m_usd, output_per_1m_usd]
     */
    public function costRates(LlmModel $model): array
    {
        // Pricing is resolved entirely from the flexible `pricing` JSON column.
        $pricing = is_array($model->pricing) ? $model->pricing : [];

        $input =
            $this->coercePer1mRate($pricing, [
                'input_per_1m_tokens',
                'prompt_per_1m_tokens',
            ]) ??
            ($this->coercePer1kRate($pricing, [
                'input_per_1k_tokens',
                'prompt_per_1k_tokens',
            ]) ??
                0.0);

        $output =
            $this->coercePer1mRate($pricing, [
                'output_per_1m_tokens',
                'completion_per_1m_tokens',
            ]) ??
            ($this->coercePer1kRate($pricing, [
                'output_per_1k_tokens',
                'completion_per_1k_tokens',
            ]) ??
                0.0);

        return [$input, $output];
    }

    /**
     * Compute the cost we incur (for margin reporting).
     */
    public function costCents(
        LlmModel $model,
        int $tokenInput,
        int $tokenOutput,
    ): int {
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
            if (
                isset($pricing[$key]) &&
                is_numeric($pricing[$key]) &&
                (float) $pricing[$key] >= 0
            ) {
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
            if (
                isset($pricing[$key]) &&
                is_numeric($pricing[$key]) &&
                (float) $pricing[$key] >= 0
            ) {
                return (float) $pricing[$key] * 1000;
            }
        }

        return null;
    }
}
