<?php

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use Illuminate\Support\Facades\Schema;

it('creates llm platform tables with key columns', function () {
    expect(Schema::hasTable('llm_providers'))->toBeTrue();
    expect(Schema::hasTable('llm_models'))->toBeTrue();
    expect(Schema::hasTable('mcp_servers'))->toBeTrue();
    expect(Schema::hasTable('plan_provider_entitlements'))->toBeTrue();
    expect(Schema::hasTable('plan_model_entitlements'))->toBeTrue();
    expect(Schema::hasTable('team_quota_policies'))->toBeTrue();
    expect(Schema::hasTable('api_keys'))->toBeTrue();
    expect(Schema::hasTable('usage_ledgers'))->toBeTrue();
    expect(Schema::hasTable('request_logs'))->toBeTrue();
    expect(Schema::hasTable('billing_invoices'))->toBeTrue();
    expect(Schema::hasTable('billing_invoice_items'))->toBeTrue();
    expect(Schema::hasTable('subscriptions'))->toBeTrue();

    expect(Schema::hasColumns('api_keys', ['team_id', 'key_hash', 'last_four', 'revoked_at']))->toBeTrue();
    expect(Schema::hasColumns('team_quota_policies', ['daily_token_limit', 'weekly_token_limit', 'monthly_token_limit']))->toBeTrue();
    expect(Schema::hasColumns('request_logs', ['protocol', 'is_streaming', 'tool_calls_count', 'latency_ms']))->toBeTrue();
    expect(Schema::hasColumns('billing_invoices', ['team_id', 'invoice_number', 'billing_month', 'status', 'total_cents']))->toBeTrue();
    expect(Schema::hasColumns('billing_invoice_items', ['billing_invoice_id', 'llm_model_id', 'token_total', 'line_subtotal_cents']))->toBeTrue();
    expect(Schema::hasColumns('subscriptions', ['team_id', 'stripe_id', 'stripe_status', 'stripe_price']))->toBeTrue();
});

it('grants admin role the llm platform management permissions', function () {
    $permissions = TeamRole::Admin->permissions();

    expect($permissions)->toContain(TeamPermission::ManageApiKeys);
    expect($permissions)->toContain(TeamPermission::ManageQuota);
    expect($permissions)->toContain(TeamPermission::ViewUsage);
    expect($permissions)->toContain(TeamPermission::ManageGatewayConfig);
    expect($permissions)->toContain(TeamPermission::ManageEntitlements);
    expect($permissions)->toContain(TeamPermission::ViewBilling);
    expect($permissions)->toContain(TeamPermission::ManageBilling);
});
