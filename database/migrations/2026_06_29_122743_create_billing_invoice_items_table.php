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
        Schema::create('billing_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('llm_model_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->unsignedBigInteger('token_input')->default(0);
            $table->unsignedBigInteger('token_output')->default(0);
            $table->unsignedBigInteger('token_total')->default(0);
            $table->unsignedBigInteger('unit_amount_micros')->default(0);
            $table->unsignedBigInteger('line_subtotal_cents')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['billing_invoice_id', 'llm_model_id'], 'billing_item_invoice_model_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_invoice_items');
    }
};
