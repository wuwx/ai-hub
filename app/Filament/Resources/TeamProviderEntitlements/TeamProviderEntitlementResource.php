<?php

namespace App\Filament\Resources\TeamProviderEntitlements;

use App\Enums\TeamPermission;
use App\Filament\Resources\TeamProviderEntitlements\Pages\CreateTeamProviderEntitlement;
use App\Filament\Resources\TeamProviderEntitlements\Pages\EditTeamProviderEntitlement;
use App\Filament\Resources\TeamProviderEntitlements\Pages\ListTeamProviderEntitlements;
use App\Filament\Resources\TeamProviderEntitlements\Schemas\TeamProviderEntitlementForm;
use App\Filament\Resources\TeamProviderEntitlements\Tables\TeamProviderEntitlementsTable;
use App\Models\TeamProviderEntitlement;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class TeamProviderEntitlementResource extends Resource
{
    protected static ?string $model = TeamProviderEntitlement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Team Access';

    public static function canViewAny(): bool
    {
        return static::hasCurrentTeam();
    }

    public static function canCreate(): bool
    {
        return static::canManageEntitlements();
    }

    public static function canEdit($record): bool
    {
        return static::canManageEntitlements();
    }

    public static function canDelete($record): bool
    {
        return static::canManageEntitlements();
    }

    public static function form(Schema $schema): Schema
    {
        return TeamProviderEntitlementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TeamProviderEntitlementsTable::configure($table);
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
            'index' => ListTeamProviderEntitlements::route('/'),
            'create' => CreateTeamProviderEntitlement::route('/create'),
            'edit' => EditTeamProviderEntitlement::route('/{record}/edit'),
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

    protected static function canManageEntitlements(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();
        $team = $user?->currentTeam;

        if (! $user || ! $team) {
            return false;
        }

        return $user->hasTeamPermission($team, TeamPermission::ManageEntitlements);
    }
}
