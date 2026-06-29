<?php

namespace App\Filament\Resources\LlmProviders\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LlmProviderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                Select::make('adapter_type')
                    ->options([
                        'openai_compatible' => 'OpenAI Compatible',
                        'anthropic_compatible' => 'Anthropic Compatible',
                        'custom' => 'Custom',
                    ])
                    ->required()
                    ->default('openai_compatible'),
                TextInput::make('base_url')
                    ->url()
                    ->required(),
                Select::make('auth_mode')
                    ->options([
                        'bearer' => 'Bearer',
                        'header' => 'Header',
                        'none' => 'None',
                    ])
                    ->required()
                    ->default('bearer'),
                TextInput::make('secret_ref'),
                KeyValue::make('options')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
