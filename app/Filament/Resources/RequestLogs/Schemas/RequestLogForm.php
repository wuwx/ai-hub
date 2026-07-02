<?php

namespace App\Filament\Resources\RequestLogs\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RequestLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('trace_id'),
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Select::make('api_key_id')
                    ->relationship('apiKey', 'name'),
                Select::make('llm_provider_id')
                    ->relationship('provider', 'name'),
                Select::make('llm_model_id')
                    ->relationship('llmModel', 'name'),
                TextInput::make('protocol')
                    ->required(),
                TextInput::make('endpoint')
                    ->required(),
                TextInput::make('http_method')
                    ->required(),
                Toggle::make('is_streaming')
                    ->required(),
                TextInput::make('tool_calls_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('status_code')
                    ->numeric(),
                TextInput::make('token_input')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('token_output')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('token_total')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('latency_ms')
                    ->numeric(),
                TextInput::make('error_code'),
                Textarea::make('error_message')
                    ->columnSpanFull(),
                DateTimePicker::make('requested_at')
                    ->required(),
            ])
            ->disabled();
    }
}
