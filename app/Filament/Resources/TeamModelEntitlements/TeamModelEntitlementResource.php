<?php

namespace App\Filament\Resources\TeamModelEntitlements;

use App\Enums\TeamPermission;
use App\Filament\Resources\TeamModelEntitlements\Pages\CreateTeamModelEntitlement;
use App\Filament\Resources\TeamModelEntitlements\Pages\EditTeamModelEntitlement;
use App\Filament\Resources\TeamModelEntitlements\Pages\ListTeamModelEntitlements;
use App\Filament\Resources\TeamModelEntitlements\Schemas\TeamModelEntitlementForm;
use App\Filament\Resources\TeamModelEntitlements\Tables\TeamModelEntitlementsTable;
use App\Models\TeamModelEntitlement;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class TeamModelEntitlementResource extends Resource
{
    protected static ?string $model = TeamModelEntitlement::class;

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
        return TeamModelEntitlementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TeamModelEntitlementsTable::configure($table);
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
            'index' => ListTeamModelEntitlements::route('/'),
            'create' => CreateTeamModelEntitlement::route('/create'),
            'edit' => EditTeamModelEntitlement::route('/{record}/edit'),
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
