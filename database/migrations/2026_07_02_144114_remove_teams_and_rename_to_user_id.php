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
