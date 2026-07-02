<?php

namespace App\Filament\Resources\QuotaPolicies\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class QuotaPoliciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('plan_code')
                    ->searchable(),
                TextColumn::make('daily_token_limit')
                    ->label('Daily Token Limit')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('weekly_token_limit')
                    ->label('Weekly Token Limit')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('monthly_token_limit')
                    ->label('Monthly Token Limit')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('daily_alert_threshold')
                    ->label('Daily Alert %')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('monthly_alert_threshold')
                    ->label('Monthly Alert %')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('effective_from')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('effective_to')
                    ->dateTime()
                    ->sortable(),
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
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
