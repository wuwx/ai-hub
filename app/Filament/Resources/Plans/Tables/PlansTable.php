<?php

namespace App\Filament\Resources\Plans\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('monthly_price_cents')
                    ->label('Price (cents)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('daily_token_limit')
                    ->label('Daily Limit')
                    ->numeric()
                    ->sortable()
                    ->default('∞'),
                TextColumn::make('weekly_token_limit')
                    ->label('Weekly Limit')
                    ->numeric()
                    ->sortable()
                    ->default('∞'),
                TextColumn::make('monthly_token_limit')
                    ->label('Monthly Limit')
                    ->numeric()
                    ->sortable()
                    ->default('∞'),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
