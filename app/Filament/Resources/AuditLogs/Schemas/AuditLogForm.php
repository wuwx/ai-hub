<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AuditLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('action')
                    ->required(),
                TextInput::make('actor.name')
                    ->label('Actor'),
                TextInput::make('subject_type'),
                TextInput::make('subject_id'),
                TextInput::make('ip_address'),
                TextInput::make('user_agent')
                    ->columnSpanFull(),
                KeyValue::make('properties')
                    ->columnSpanFull(),
            ])
            ->disabled();
    }
}
