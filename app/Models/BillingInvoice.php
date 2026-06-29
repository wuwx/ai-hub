<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'team_id',
    'invoice_number',
    'billing_month',
    'currency',
    'status',
    'payment_provider',
    'payment_reference',
    'payment_url',
    'subtotal_cents',
    'tax_cents',
    'total_cents',
    'issued_at',
    'due_at',
    'paid_at',
    'notes',
])]
class BillingInvoice extends Model
{
    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return HasMany<BillingInvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BillingInvoiceItem::class);
    }

    public function isFinalized(): bool
    {
        return in_array($this->status, ['paid', 'void'], true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_month' => 'date',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'subtotal_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
            'payment_provider' => 'string',
            'payment_reference' => 'string',
            'payment_url' => 'string',
        ];
    }
}
