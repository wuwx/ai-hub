<?php

namespace App\Filament\Resources\ApiKeys\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ApiKeyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                DateTimePicker::make('last_used_at'),
                DateTimePicker::make('expires_at'),
                DateTimePicker::make('revoked_at')
                    ->disabled(),
            ]);
    }
}
