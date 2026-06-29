<?php

namespace App\Filament\Resources\McpServers;

use App\Enums\TeamPermission;
use App\Filament\Resources\McpServers\Pages\CreateMcpServer;
use App\Filament\Resources\McpServers\Pages\EditMcpServer;
use App\Filament\Resources\McpServers\Pages\ListMcpServers;
use App\Filament\Resources\McpServers\Schemas\McpServerForm;
use App\Filament\Resources\McpServers\Tables\McpServersTable;
use App\Models\McpServer;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class McpServerResource extends Resource
{
    protected static ?string $model = McpServer::class;

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
        return McpServerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return McpServersTable::configure($table);
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
            'index' => ListMcpServers::route('/'),
            'create' => CreateMcpServer::route('/create'),
            'edit' => EditMcpServer::route('/{record}/edit'),
        ];
    }

    protected static function canManageGatewayConfiguration(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();
        $team = $user?->currentTeam;

        if (! $user || ! $team) {
            return false;
        }

        return $user->hasTeamPermission($team, TeamPermission::ManageGatewayConfig);
    }
}
