<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('description')
                    ->label('Action')
                    ->searchable()
                    ->sortable()
                    ->badge(),
                TextColumn::make('causer.name')
                    ->label('Actor')
                    ->searchable(),
                TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(fn ($state, $record) => $state ? class_basename($state).' #'.$record->subject_id : '—'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([])
            ->toolbarActions([]);
    }
}
