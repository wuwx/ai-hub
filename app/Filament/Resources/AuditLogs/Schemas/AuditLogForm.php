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
                TextInput::make('description')
                    ->label('Action')
                    ->required(),
                TextInput::make('causer.name')
                    ->label('Actor'),
                TextInput::make('subject_type'),
                TextInput::make('subject_id'),
                KeyValue::make('properties')
                    ->columnSpanFull(),
            ])
            ->disabled();
    }
}
