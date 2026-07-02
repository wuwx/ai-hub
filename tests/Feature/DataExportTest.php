<?php

use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\UsageLedger;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;

    // Create usage data
    $provider = LlmProvider::create([
        'name' => 'OpenAI Mock',
        'slug' => 'export-test-'.uniqid(),
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://openai.mock',
        'auth_mode' => 'bearer',
        'secret_ref' => 'test-secret',
        'is_active' => true,
    ]);

    $model = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'GPT-4.1',
        'external_model_id' => 'gpt-4.1',
        'is_active' => true,
    ]);

    UsageLedger::create([
        'team_id' => $this->team->id,
        'llm_provider_id' => $provider->id,
        'llm_model_id' => $model->id,
        'bucket_date' => now()->toDateString(),
        'bucket_type' => 'day',
        'token_input' => 500,
        'token_output' => 300,
        'token_total' => 800,
        'request_count' => 10,
        'error_count' => 1,
    ]);
});

it('exports usage data as CSV', function () {
    $response = $this->actingAs($this->user)->get(
        "/{$this->team->slug}/usage/export",
    );

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $response->assertHeader('Content-Disposition');

    $content = $response->getContent();
    expect($content)
        ->toContain('Date')
        ->and($content)
        ->toContain('Model')
        ->and($content)
        ->toContain('Provider')
        ->and($content)
        ->toContain('GPT-4.1')
        ->and($content)
        ->toContain('500')
        ->and($content)
        ->toContain('800');
});

it('requires authentication to export data', function () {
    $this->get("/{$this->team->slug}/usage/export")->assertRedirect('/login');
});

it('requires team membership to export', function () {
    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)
        ->get("/{$this->team->slug}/usage/export")
        ->assertForbidden();
});
