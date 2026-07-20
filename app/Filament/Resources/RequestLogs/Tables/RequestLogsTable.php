<?php

namespace App\Filament\Resources\RequestLogs\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RequestLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('trace_id')
                    ->searchable(),
                TextColumn::make('team.name')
                    ->searchable(),
                TextColumn::make('token.name')
                    ->searchable(),
                TextColumn::make('provider.name')
                    ->searchable(),
                TextColumn::make('llmModel.name')
                    ->searchable(),
                TextColumn::make('protocol')
                    ->searchable(),
                TextColumn::make('endpoint')
                    ->searchable(),
                TextColumn::make('http_method')
                    ->searchable(),
                IconColumn::make('is_streaming')
                    ->boolean(),
                TextColumn::make('tool_calls_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status_code')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('token_input')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('token_output')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('token_total')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('latency_ms')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('error_code')
                    ->searchable(),
                TextColumn::make('requested_at')
                    ->dateTime()
                    ->sortable(),
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
            ->recordActions([])
            ->toolbarActions([]);
    }
}
