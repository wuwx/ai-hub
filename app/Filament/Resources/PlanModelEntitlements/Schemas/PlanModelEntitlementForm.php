<?php

namespace App\Filament\Resources\PlanModelEntitlements\Schemas;

use App\Models\Plan;
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
                    ->options(fn (): array => Plan::query()
                        ->active()
                        ->orderBy('sort_order')
                        ->pluck('name', 'code')
                        ->all())
                    ->required()
                    ->searchable(),
                Select::make('llm_model_id')
                    ->relationship('llmModel', 'name')
                    ->required()
                    ->searchable(),
                Toggle::make('is_enabled')
                    ->required(),
            ]);
    }
}
