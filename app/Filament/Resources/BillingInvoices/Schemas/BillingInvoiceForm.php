<?php

namespace App\Filament\Resources\BillingInvoices\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BillingInvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('invoice_number')
                    ->disabled(),
                TextInput::make('billing_month')
                    ->disabled(),
                Select::make('status')
                    ->required()
                    ->options([
                        'draft' => 'Draft',
                        'issued' => 'Issued',
                        'overdue' => 'Overdue',
                        'paid' => 'Paid',
                        'void' => 'Void',
                    ]),
                TextInput::make('payment_reference')
                    ->disabled(),
                TextInput::make('payment_url')
                    ->url()
                    ->disabled(),
                TextInput::make('currency')
                    ->required()
                    ->disabled(),
                TextInput::make('subtotal_cents')
                    ->label('Subtotal (cents)')
                    ->numeric()
                    ->disabled(),
                TextInput::make('tax_cents')
                    ->label('Tax (cents)')
                    ->numeric()
                    ->disabled(),
                TextInput::make('total_cents')
                    ->label('Total (cents)')
                    ->numeric()
                    ->disabled(),
                DateTimePicker::make('issued_at')
                    ->disabled(),
                DateTimePicker::make('due_at'),
                DateTimePicker::make('paid_at'),
                Textarea::make('notes')
                    ->maxLength(2000),
            ]);
    }
}
