<?php

namespace App\Filament\Resources\BillingInvoices\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BillingInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('billing_month', 'desc')
            ->columns([
                TextColumn::make('invoice_number')
                    ->searchable(),
                TextColumn::make('billing_month')
                    ->date('Y-m')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('payment_reference')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('total_cents')
                    ->label('Total')
                    ->formatStateUsing(fn (int $state, $record): string => sprintf('%s %0.2f', $record->currency, $state / 100))
                    ->sortable(),
                TextColumn::make('due_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
