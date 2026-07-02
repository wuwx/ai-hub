<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add Cashier (Billable) columns to users table.
        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_id')->nullable()->index()->after('remember_token');
            $table->string('pm_type')->nullable()->after('stripe_id');
            $table->string('pm_last_four', 4)->nullable()->after('pm_type');
            $table->timestamp('trial_ends_at')->nullable()->after('pm_last_four');
        });

        // 2. Rename team_id -> user_id in dependent tables.
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropIndex(['team_id', 'revoked_at']);
            $table->renameColumn('team_id', 'user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'revoked_at']);
        });

        Schema::table('usage_ledgers', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropIndex('usage_ledger_team_bucket_idx');
            $table->renameColumn('team_id', 'user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'bucket_type', 'bucket_date'], 'usage_ledger_user_bucket_idx');
        });

        Schema::table('request_logs', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropIndex('request_logs_team_created_idx');
            $table->renameColumn('team_id', 'user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'created_at'], 'request_logs_user_created_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropIndex('audit_logs_team_created_idx');
            $table->renameColumn('team_id', 'user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'created_at'], 'audit_logs_user_created_idx');
        });

        // 3. Rename team_quota_policies -> quota_policies and team_id -> user_id.
        Schema::table('team_quota_policies', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropIndex(['team_id', 'is_active']);
            $table->dropIndex('team_quota_policy_effective_idx');
            $table->renameColumn('team_id', 'user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'effective_from', 'effective_to'], 'quota_policy_effective_idx');
        });
        Schema::rename('team_quota_policies', 'quota_policies');

        // 4. Rename team_webhook_endpoints -> webhook_endpoints and team_id -> user_id.
        // First drop the foreign key in webhook_deliveries.
        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->dropForeign(['team_webhook_endpoint_id']);
            $table->dropIndex('webhook_deliveries_endpoint_created_idx');
            $table->renameColumn('team_webhook_endpoint_id', 'webhook_endpoint_id');
        });

        Schema::table('team_webhook_endpoints', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropIndex('team_webhooks_team_active_idx');
            $table->renameColumn('team_id', 'user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'is_active'], 'webhooks_user_active_idx');
        });
        Schema::rename('team_webhook_endpoints', 'webhook_endpoints');

        // Re-add the foreign key in webhook_deliveries pointing to the renamed table.
        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->foreign('webhook_endpoint_id')->references('id')->on('webhook_endpoints')->cascadeOnDelete();
            $table->index(['webhook_endpoint_id', 'created_at'], 'webhook_deliveries_endpoint_created_idx');
        });

        // 5. Rename team_id -> user_id in subscriptions table.
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->renameColumn('team_id', 'user_id');
            $table->dropIndex(['team_id', 'stripe_status']);
            $table->index(['user_id', 'stripe_status']);
        });

        // 6. Drop current_team_id from users.
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_team_id');
        });

        // 7. Drop team-related tables.
        Schema::dropIfExists('team_invitations');
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate teams table.
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_personal')->default(false);
            $table->string('stripe_id')->nullable()->index();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();
            $table->unique(['team_id', 'user_id']);
        });

        Schema::create('team_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role');
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });

        // Re-add current_team_id to users.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_team_id')
                ->nullable()
                ->after('password')
                ->constrained('teams')
                ->nullOnDelete();
        });

        // Rename webhook_endpoints back to team_webhook_endpoints.
        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->dropForeign(['webhook_endpoint_id']);
            $table->dropIndex('webhook_deliveries_endpoint_created_idx');
            $table->renameColumn('webhook_endpoint_id', 'team_webhook_endpoint_id');
        });

        Schema::rename('webhook_endpoints', 'team_webhook_endpoints');
        Schema::table('team_webhook_endpoints', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex('webhooks_user_active_idx');
            $table->renameColumn('user_id', 'team_id');
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->index(['team_id', 'is_active'], 'team_webhooks_team_active_idx');
        });

        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->foreign('team_webhook_endpoint_id')->references('id')->on('team_webhook_endpoints')->cascadeOnDelete();
            $table->index(['team_webhook_endpoint_id', 'created_at'], 'webhook_deliveries_endpoint_created_idx');
        });

        // Rename quota_policies back to team_quota_policies.
        Schema::rename('quota_policies', 'team_quota_policies');
        Schema::table('team_quota_policies', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id', 'is_active']);
            $table->dropIndex('quota_policy_effective_idx');
            $table->renameColumn('user_id', 'team_id');
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->index(['team_id', 'is_active']);
            $table->index(['team_id', 'effective_from', 'effective_to'], 'team_quota_policy_effective_idx');
        });

        // Rename user_id back to team_id in subscriptions table.
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'stripe_status']);
            $table->renameColumn('user_id', 'team_id');
            $table->index(['team_id', 'stripe_status']);
        });

        // Rename user_id back to team_id in dependent tables.
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex('audit_logs_user_created_idx');
            $table->renameColumn('user_id', 'team_id');
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->index(['team_id', 'created_at'], 'audit_logs_team_created_idx');
        });

        Schema::table('request_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex('request_logs_user_created_idx');
            $table->renameColumn('user_id', 'team_id');
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->index(['team_id', 'created_at'], 'request_logs_team_created_idx');
        });

        Schema::table('usage_ledgers', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex('usage_ledger_user_bucket_idx');
            $table->renameColumn('user_id', 'team_id');
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->index(['team_id', 'bucket_type', 'bucket_date'], 'usage_ledger_team_bucket_idx');
        });

        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id', 'revoked_at']);
            $table->renameColumn('user_id', 'team_id');
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->index(['team_id', 'revoked_at']);
        });

        // Remove Cashier columns from users.
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['stripe_id']);
            $table->dropColumn(['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at']);
        });
    }
};
