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
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->string('payment_provider', 32)->nullable()->after('status');
            $table->string('payment_reference')->nullable()->after('payment_provider');
            $table->text('payment_url')->nullable()->after('payment_reference');

            $table->index(['payment_provider', 'payment_reference'], 'billing_invoice_payment_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->dropIndex('billing_invoice_payment_idx');
            $table->dropColumn(['payment_provider', 'payment_reference', 'payment_url']);
        });
    }
};
