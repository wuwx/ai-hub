<?php

namespace App\Filament\Resources\PlanModelEntitlements;

use App\Filament\Resources\PlanModelEntitlements\Pages\CreatePlanModelEntitlement;
use App\Filament\Resources\PlanModelEntitlements\Pages\EditPlanModelEntitlement;
use App\Filament\Resources\PlanModelEntitlements\Pages\ListPlanModelEntitlements;
use App\Filament\Resources\PlanModelEntitlements\Schemas\PlanModelEntitlementForm;
use App\Filament\Resources\PlanModelEntitlements\Tables\PlanModelEntitlementsTable;
use App\Models\PlanModelEntitlement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PlanModelEntitlementResource extends Resource
{
    protected static ?string $model = PlanModelEntitlement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Plan Access';

    public static function form(Schema $schema): Schema
    {
        return PlanModelEntitlementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlanModelEntitlementsTable::configure($table);
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
            'index' => ListPlanModelEntitlements::route('/'),
            'create' => CreatePlanModelEntitlement::route('/create'),
            'edit' => EditPlanModelEntitlement::route('/{record}/edit'),
        ];
    }
}
