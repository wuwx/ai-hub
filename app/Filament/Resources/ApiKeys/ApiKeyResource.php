<?php

namespace App\Filament\Resources\ApiKeys;

use App\Enums\TeamPermission;
use App\Filament\Resources\ApiKeys\Pages\CreateApiKey;
use App\Filament\Resources\ApiKeys\Pages\EditApiKey;
use App\Filament\Resources\ApiKeys\Pages\ListApiKeys;
use App\Filament\Resources\ApiKeys\Schemas\ApiKeyForm;
use App\Filament\Resources\ApiKeys\Tables\ApiKeysTable;
use App\Models\ApiKey;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ApiKeyResource extends Resource
{
    protected static ?string $model = ApiKey::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Usage & Limits';

    public static function canViewAny(): bool
    {
        return static::hasCurrentTeam();
    }

    public static function canCreate(): bool
    {
        return static::canManageApiKeys();
    }

    public static function canEdit($record): bool
    {
        return static::canManageApiKeys();
    }

    public static function canDelete($record): bool
    {
        return static::canManageApiKeys();
    }

    public static function canAccess(array $parameters = []): bool
    {
        return static::hasCurrentTeam();
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
        $teamId = Auth::user()?->current_team_id;

        return parent::getEloquentQuery()
            ->when($teamId, fn (Builder $query) => $query->where('team_id', $teamId))
            ->when(! $teamId, fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    protected static function hasCurrentTeam(): bool
    {
        return Auth::user()?->currentTeam !== null;
    }

    protected static function canManageApiKeys(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();
        $team = $user?->currentTeam;

        if (! $user || ! $team) {
            return false;
        }

        return $user->hasTeamPermission($team, TeamPermission::ManageApiKeys);
    }
}
