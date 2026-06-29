<?php

namespace App\Filament\Resources\TeamQuotaPolicies;

use App\Enums\TeamPermission;
use App\Filament\Resources\TeamQuotaPolicies\Pages\CreateTeamQuotaPolicy;
use App\Filament\Resources\TeamQuotaPolicies\Pages\EditTeamQuotaPolicy;
use App\Filament\Resources\TeamQuotaPolicies\Pages\ListTeamQuotaPolicies;
use App\Filament\Resources\TeamQuotaPolicies\Schemas\TeamQuotaPolicyForm;
use App\Filament\Resources\TeamQuotaPolicies\Tables\TeamQuotaPoliciesTable;
use App\Models\TeamQuotaPolicy;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class TeamQuotaPolicyResource extends Resource
{
    protected static ?string $model = TeamQuotaPolicy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Usage & Limits';

    public static function canViewAny(): bool
    {
        return static::hasCurrentTeam();
    }

    public static function canCreate(): bool
    {
        return static::canManageQuota();
    }

    public static function canEdit($record): bool
    {
        return static::canManageQuota();
    }

    public static function canDelete($record): bool
    {
        return static::canManageQuota();
    }

    public static function form(Schema $schema): Schema
    {
        return TeamQuotaPolicyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TeamQuotaPoliciesTable::configure($table);
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
            'index' => ListTeamQuotaPolicies::route('/'),
            'create' => CreateTeamQuotaPolicy::route('/create'),
            'edit' => EditTeamQuotaPolicy::route('/{record}/edit'),
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

    protected static function canManageQuota(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();
        $team = $user?->currentTeam;

        if (! $user || ! $team) {
            return false;
        }

        return $user->hasTeamPermission($team, TeamPermission::ManageQuota);
    }
}
