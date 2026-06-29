<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'billing_invoice_id',
    'llm_model_id',
    'description',
    'token_input',
    'token_output',
    'token_total',
    'unit_amount_micros',
    'line_subtotal_cents',
    'metadata',
])]
class BillingInvoiceItem extends Model
{
    /**
     * @return BelongsTo<BillingInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'billing_invoice_id');
    }

    /**
     * @return BelongsTo<LlmModel, $this>
     */
    public function llmModel(): BelongsTo
    {
        return $this->belongsTo(LlmModel::class, 'llm_model_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'token_input' => 'integer',
            'token_output' => 'integer',
            'token_total' => 'integer',
            'unit_amount_micros' => 'integer',
            'line_subtotal_cents' => 'integer',
        ];
    }
}
