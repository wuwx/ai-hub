<?php

namespace App\Filament\Resources\QuotaPolicies;

use App\Filament\Resources\QuotaPolicies\Pages\CreateQuotaPolicy;
use App\Filament\Resources\QuotaPolicies\Pages\EditQuotaPolicy;
use App\Filament\Resources\QuotaPolicies\Pages\ListQuotaPolicies;
use App\Filament\Resources\QuotaPolicies\Schemas\QuotaPolicyForm;
use App\Filament\Resources\QuotaPolicies\Tables\QuotaPoliciesTable;
use App\Models\QuotaPolicy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class QuotaPolicyResource extends Resource
{
    protected static ?string $model = QuotaPolicy::class;

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

    public static function form(Schema $schema): Schema
    {
        return QuotaPolicyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuotaPoliciesTable::configure($table);
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
            'index' => ListQuotaPolicies::route('/'),
            'create' => CreateQuotaPolicy::route('/create'),
            'edit' => EditQuotaPolicy::route('/{record}/edit'),
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
