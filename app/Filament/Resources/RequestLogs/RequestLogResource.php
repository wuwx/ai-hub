<?php

namespace App\Filament\Resources\RequestLogs;

use App\Enums\TeamPermission;
use App\Filament\Resources\RequestLogs\Pages\ListRequestLogs;
use App\Filament\Resources\RequestLogs\Schemas\RequestLogForm;
use App\Filament\Resources\RequestLogs\Tables\RequestLogsTable;
use App\Models\RequestLog;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class RequestLogResource extends Resource
{
    protected static ?string $model = RequestLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Usage & Limits';

    public static function canViewAny(): bool
    {
        return static::canViewUsage();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return RequestLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RequestLogsTable::configure($table);
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
            'index' => ListRequestLogs::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $teamId = Auth::user()?->current_team_id;

        return parent::getEloquentQuery()
            ->when($teamId, fn (Builder $query) => $query->where('team_id', $teamId))
            ->when(! $teamId, fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    protected static function canViewUsage(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();
        $team = $user?->currentTeam;

        if (! $user || ! $team) {
            return false;
        }

        return $user->hasTeamPermission($team, TeamPermission::ViewUsage);
    }
}
