<?php

namespace App\Filament\Resources\BillingInvoices;

use App\Enums\TeamPermission;
use App\Filament\Resources\BillingInvoices\Pages\EditBillingInvoice;
use App\Filament\Resources\BillingInvoices\Pages\ListBillingInvoices;
use App\Filament\Resources\BillingInvoices\Schemas\BillingInvoiceForm;
use App\Filament\Resources\BillingInvoices\Tables\BillingInvoicesTable;
use App\Models\BillingInvoice;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class BillingInvoiceResource extends Resource
{
    protected static ?string $model = BillingInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Billing & Revenue';

    public static function canViewAny(): bool
    {
        return static::canViewBilling();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return static::canManageBilling();
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return BillingInvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BillingInvoicesTable::configure($table);
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
            'index' => ListBillingInvoices::route('/'),
            'edit' => EditBillingInvoice::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $teamId = Auth::user()?->current_team_id;

        return parent::getEloquentQuery()
            ->with(['team'])
            ->when($teamId, fn (Builder $query) => $query->where('team_id', $teamId))
            ->when(! $teamId, fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    protected static function canViewBilling(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();
        $team = $user?->currentTeam;

        if (! $user || ! $team) {
            return false;
        }

        return $user->hasTeamPermission($team, TeamPermission::ViewBilling)
            || $user->hasTeamPermission($team, TeamPermission::ManageBilling);
    }

    protected static function canManageBilling(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();
        $team = $user?->currentTeam;

        if (! $user || ! $team) {
            return false;
        }

        return $user->hasTeamPermission($team, TeamPermission::ManageBilling);
    }
}
