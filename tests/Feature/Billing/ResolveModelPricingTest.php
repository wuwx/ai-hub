<?php

use App\Actions\Billing\ResolveModelPricing;
use App\Models\LlmModel;
use App\Models\LlmProvider;

it('falls back to legacy pricing JSON per-1M rates', function () {
    $provider = LlmProvider::create([
        'name' => 'Test',
        'slug' => 'test-'.uniqid(),
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://test.mock',
        'auth_mode' => 'bearer',
        'is_active' => true,
    ]);

    $model = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'M',
        'external_model_id' => 'm-1',
        'pricing' => [
            'input_per_1m_tokens' => 5.0,
            'output_per_1m_tokens' => 15.0,
        ],
        'is_active' => true,
    ]);

    $resolver = new ResolveModelPricing;
    [$in, $out] = $resolver->costRates($model);

    expect($in)->toBe(5.0);
    expect($out)->toBe(15.0);
});

it('falls back to legacy pricing JSON per-1K rates by converting', function () {
    $provider = LlmProvider::create([
        'name' => 'Test',
        'slug' => 'test-'.uniqid(),
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://test.mock',
        'auth_mode' => 'bearer',
        'is_active' => true,
    ]);

    $model = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'M',
        'external_model_id' => 'm-1',
        'pricing' => [
            'input_per_1k_tokens' => 0.01,
            'output_per_1k_tokens' => 0.03,
        ],
        'is_active' => true,
    ]);

    $resolver = new ResolveModelPricing;
    [$in, $out] = $resolver->costRates($model);

    expect($in)->toBe(10.0); // 0.01 * 1000
    expect($out)->toBe(30.0); // 0.03 * 1000
});

it('costCents computes the upstream cost for margin reporting', function () {
    $provider = LlmProvider::create([
        'name' => 'Test',
        'slug' => 'test-'.uniqid(),
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://test.mock',
        'auth_mode' => 'bearer',
        'is_active' => true,
    ]);

    $model = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'M',
        'external_model_id' => 'm-1',
        'cost_input_per_1m_usd' => 0.5,
        'cost_output_per_1m_usd' => 1.0,
        'is_active' => true,
    ]);

    $resolver = new ResolveModelPricing;

    // 500K input + 250K output = $0.25 + $0.25 = $0.50 = 50 cents
    expect($resolver->costCents($model, 500_000, 250_000))->toBe(50);
});
