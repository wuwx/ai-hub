<?php

namespace App\Filament\Resources\McpServers\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class McpServerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('endpoint')
                    ->required(),
                Select::make('transport')
                    ->options([
                        'streamable_http' => 'Streamable HTTP',
                        'sse' => 'SSE',
                        'websocket' => 'WebSocket',
                    ])
                    ->required()
                    ->default('streamable_http'),
                Select::make('auth_mode')
                    ->options([
                        'none' => 'None',
                        'bearer' => 'Bearer',
                        'header' => 'Header',
                    ])
                    ->required()
                    ->default('none'),
                TextInput::make('secret_ref'),
                KeyValue::make('headers')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('last_health_status'),
                DateTimePicker::make('last_health_checked_at'),
            ]);
    }
}
