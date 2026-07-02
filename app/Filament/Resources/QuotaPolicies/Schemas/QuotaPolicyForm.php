<?php

namespace App\Filament\Resources\QuotaPolicies\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class QuotaPolicyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('user_id')
                    ->default(fn () => Auth::id()),
                TextInput::make('daily_token_limit')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('weekly_token_limit')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('monthly_token_limit')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('daily_alert_threshold')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100)
                    ->default(80),
                TextInput::make('monthly_alert_threshold')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100)
                    ->default(80),
                DateTimePicker::make('effective_from')
                    ->required(),
                DateTimePicker::make('effective_to'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
