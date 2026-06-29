<?php

namespace App\Filament\Resources\TeamProviderEntitlements\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class TeamProviderEntitlementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('team_id')
                    ->default(fn () => Auth::user()?->current_team_id),
                Select::make('llm_provider_id')
                    ->relationship('provider', 'name')
                    ->required(),
                Toggle::make('is_enabled')
                    ->required(),
            ]);
    }
}
