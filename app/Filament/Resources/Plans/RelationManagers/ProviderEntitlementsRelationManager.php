<?php

namespace App\Filament\Resources\Plans\RelationManagers;

use App\Models\Plan;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProviderEntitlementsRelationManager extends RelationManager
{
    protected static string $relationship = 'providerEntitlements';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('llm_provider_id')
                    ->relationship('provider', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Toggle::make('is_enabled')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        /** @var Plan $owner */
        $owner = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('provider.name')
            ->columns([
                TextColumn::make('provider.name')
                    ->label('Provider')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('provider.slug')
                    ->label('Slug')
                    ->searchable(),
                IconColumn::make('is_enabled')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->form(fn (AttachAction $action): array => [
                        Select::make('llm_provider_id')
                            ->label('Provider')
                            ->relationship(
                                'provider',
                                'name',
                                fn ($query) => $query->whereNotIn(
                                    'id',
                                    $owner->providerEntitlements()->pluck('llm_provider_id'),
                                ),
                            )
                            ->required()
                            ->searchable()
                            ->preload(),
                        Toggle::make('is_enabled')
                            ->default(true),
                    ]),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->toolbarActions([]);
    }
}
