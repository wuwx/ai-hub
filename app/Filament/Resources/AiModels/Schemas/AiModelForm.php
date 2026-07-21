<?php

namespace App\Filament\Resources\AiModels\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AiModelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('ai_provider_id')
                    ->relationship('provider', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('external_model_id')
                    ->required(),
                KeyValue::make('capabilities')
                    ->columnSpanFull(),
                KeyValue::make('pricing')
                    ->columnSpanFull(),
                TextInput::make('context_window')
                    ->numeric(),
                TextInput::make('max_output_tokens')
                    ->numeric(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
