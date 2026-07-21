<?php

namespace App\Filament\Resources\AiModels;

use App\Filament\Resources\AiModels\Pages\CreateAiModel;
use App\Filament\Resources\AiModels\Pages\EditAiModel;
use App\Filament\Resources\AiModels\Pages\ListAiModels;
use App\Filament\Resources\AiModels\Schemas\AiModelForm;
use App\Filament\Resources\AiModels\Tables\AiModelsTable;
use App\Models\AiModel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AiModelResource extends Resource
{
    protected static ?string $model = AiModel::class;

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

    public static function canEdit(Model $record): bool
    {
        return static::canManageGatewayConfiguration();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canManageGatewayConfiguration();
    }

    public static function form(Schema $schema): Schema
    {
        return AiModelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AiModelsTable::configure($table);
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
            'index' => ListAiModels::route('/'),
            'create' => CreateAiModel::route('/create'),
            'edit' => EditAiModel::route('/{record}/edit'),
        ];
    }

    protected static function canManageGatewayConfiguration(): bool
    {
        return Auth::check();
    }
}
