<?php

namespace App\Filament\Resources\ApiKeys;

use App\Filament\Resources\ApiKeys\Pages\CreateApiKey;
use App\Filament\Resources\ApiKeys\Pages\EditApiKey;
use App\Filament\Resources\ApiKeys\Pages\ListApiKeys;
use App\Filament\Resources\ApiKeys\Schemas\ApiKeyForm;
use App\Filament\Resources\ApiKeys\Tables\ApiKeysTable;
use App\Models\ApiKey;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ApiKeyResource extends Resource
{
    protected static ?string $model = ApiKey::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Usage & Limits';

    public static function canViewAny(): bool
    {
        return Auth::check();
    }

    public static function canCreate(): bool
    {
        return Auth::check();
    }

    public static function canEdit(Model $record): bool
    {
        return Auth::check();
    }

    public static function canDelete(Model $record): bool
    {
        return Auth::check();
    }

    /**
     * @param  array<mixed>  $parameters
     */
    public static function canAccess(array $parameters = []): bool
    {
        return Auth::check();
    }

    public static function form(Schema $schema): Schema
    {
        return ApiKeyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ApiKeysTable::configure($table);
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
            'index' => ListApiKeys::route('/'),
            'create' => CreateApiKey::route('/create'),
            'edit' => EditApiKey::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $userId = Auth::id();

        return parent::getEloquentQuery()
            ->when($userId, fn (Builder $query) => $query->where('user_id', $userId))
            ->when(! $userId, fn (Builder $query) => $query->whereRaw('1 = 0'));
    }
}
