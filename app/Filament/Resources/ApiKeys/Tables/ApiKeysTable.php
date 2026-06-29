<?php

namespace App\Filament\Resources\ApiKeys\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ApiKeysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('team.name')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('last_four')
                    ->label('Key')
                    ->formatStateUsing(fn (?string $state): string => $state ? 'ahk_***'.$state : '-')
                    ->searchable(),
                IconColumn::make('active')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => $record->isActive())
                    ->label('Active'),
                TextColumn::make('last_used_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable(),
                IconColumn::make('revoked_at')
                    ->label('Revoked')
                    ->boolean(),
                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->searchable(),
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
