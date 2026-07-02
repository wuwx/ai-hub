<?php

namespace App\Filament\Resources\Plans\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->rows(3),
                TextInput::make('monthly_price_cents')
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                TextInput::make('stripe_price_id')
                    ->maxLength(255),
                TextInput::make('daily_token_limit')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('weekly_token_limit')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('monthly_token_limit')
                    ->numeric()
                    ->minValue(0),
                Repeater::make('features')
                    ->schema([
                        TextInput::make('feature')
                            ->label('Feature')
                            ->required(),
                    ])
                    ->addable()
                    ->deletable()
                    ->default([]),
                Toggle::make('is_active')
                    ->required()
                    ->default(true),
                TextInput::make('sort_order')
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
            ]);
    }
}
