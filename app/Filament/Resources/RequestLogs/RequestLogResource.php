<?php

namespace App\Filament\Resources\RequestLogs;

use App\Filament\Resources\RequestLogs\Pages\ListRequestLogs;
use App\Filament\Resources\RequestLogs\Schemas\RequestLogForm;
use App\Filament\Resources\RequestLogs\Tables\RequestLogsTable;
use App\Models\RequestLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class RequestLogResource extends Resource
{
    protected static ?string $model = RequestLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Usage & Limits';

    public static function canViewAny(): bool
    {
        return Auth::check();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
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
        $userId = Auth::id();

        return parent::getEloquentQuery()
            ->when($userId, fn (Builder $query) => $query->where('user_id', $userId))
            ->when(! $userId, fn (Builder $query) => $query->whereRaw('1 = 0'));
    }
}
