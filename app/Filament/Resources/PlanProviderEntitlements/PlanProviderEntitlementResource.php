<?php

namespace App\Filament\Resources\PlanProviderEntitlements;

use App\Filament\Resources\PlanProviderEntitlements\Pages\CreatePlanProviderEntitlement;
use App\Filament\Resources\PlanProviderEntitlements\Pages\EditPlanProviderEntitlement;
use App\Filament\Resources\PlanProviderEntitlements\Pages\ListPlanProviderEntitlements;
use App\Filament\Resources\PlanProviderEntitlements\Schemas\PlanProviderEntitlementForm;
use App\Filament\Resources\PlanProviderEntitlements\Tables\PlanProviderEntitlementsTable;
use App\Models\PlanProviderEntitlement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PlanProviderEntitlementResource extends Resource
{
    protected static ?string $model = PlanProviderEntitlement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Plan Access';

    public static function form(Schema $schema): Schema
    {
        return PlanProviderEntitlementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlanProviderEntitlementsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlanProviderEntitlements::route('/'),
            'create' => CreatePlanProviderEntitlement::route('/create'),
            'edit' => EditPlanProviderEntitlement::route('/{record}/edit'),
        ];
    }
}
