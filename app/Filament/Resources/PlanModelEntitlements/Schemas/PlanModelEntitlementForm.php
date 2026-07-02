<?php

namespace App\Filament\Resources\PlanModelEntitlements\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PlanModelEntitlementForm
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
                Select::make('llm_model_id')
                    ->relationship('llmModel', 'name')
                    ->required(),
                Toggle::make('is_enabled')
                    ->required(),
            ]);
    }
}
