<?php

namespace App\Filament\Resources\LlmModels;

use App\Filament\Resources\LlmModels\Pages\CreateLlmModel;
use App\Filament\Resources\LlmModels\Pages\EditLlmModel;
use App\Filament\Resources\LlmModels\Pages\ListLlmModels;
use App\Filament\Resources\LlmModels\Schemas\LlmModelForm;
use App\Filament\Resources\LlmModels\Tables\LlmModelsTable;
use App\Models\LlmModel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class LlmModelResource extends Resource
{
    protected static ?string $model = LlmModel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Gateway Configuration';

    public static function canViewAny(): bool
    {
        return static::canManageGatewayConfiguration();
    }

    public static function canCreate(): bool
    {
        return static::canManageGatewayConfiguration();
    }

    public static function canEdit($record): bool
    {
        return static::canManageGatewayConfiguration();
    }

    public static function canDelete($record): bool
    {
        return static::canManageGatewayConfiguration();
    }

    public static function form(Schema $schema): Schema
    {
        return LlmModelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LlmModelsTable::configure($table);
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
            'index' => ListLlmModels::route('/'),
            'create' => CreateLlmModel::route('/create'),
            'edit' => EditLlmModel::route('/{record}/edit'),
        ];
    }

    protected static function canManageGatewayConfiguration(): bool
    {
        return Auth::check();
    }
}
