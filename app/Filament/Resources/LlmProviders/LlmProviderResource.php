<?php

namespace App\Filament\Resources\LlmProviders;

use App\Enums\TeamPermission;
use App\Filament\Resources\LlmProviders\Pages\CreateLlmProvider;
use App\Filament\Resources\LlmProviders\Pages\EditLlmProvider;
use App\Filament\Resources\LlmProviders\Pages\ListLlmProviders;
use App\Filament\Resources\LlmProviders\Schemas\LlmProviderForm;
use App\Filament\Resources\LlmProviders\Tables\LlmProvidersTable;
use App\Models\LlmProvider;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class LlmProviderResource extends Resource
{
    protected static ?string $model = LlmProvider::class;

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
        return LlmProviderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LlmProvidersTable::configure($table);
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
            'index' => ListLlmProviders::route('/'),
            'create' => CreateLlmProvider::route('/create'),
            'edit' => EditLlmProvider::route('/{record}/edit'),
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
