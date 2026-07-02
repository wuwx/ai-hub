<?php

namespace App\Filament\Resources\PlanProviderEntitlements\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PlanProviderEntitlementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('plan_code')
                    ->options([
                        'free' => 'Free',
                        'pro' => 'Pro',
                        'enterprise' => 'Enterprise',
                    ])
                    ->required(),
                Select::make('llm_provider_id')
                    ->relationship('provider', 'name')
                    ->required(),
                Toggle::make('is_enabled')
                    ->required(),
            ]);
    }
}
