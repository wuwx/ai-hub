<?php

namespace App\Filament\Resources\Plans\RelationManagers;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Actions\DetachAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ModelEntitlementsRelationManager extends RelationManager
{
    protected static string $relationship = 'modelEntitlements';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('llm_model_id')
                    ->relationship('llmModel', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Toggle::make('is_enabled')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('llmModel.name')
            ->columns([
                TextColumn::make('llmModel.name')
                    ->label('Model')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('llmModel.externalModelId')
                    ->label('Model ID')
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
                        Select::make('llm_model_id')
                            ->label('Model')
                            ->relationship(
                                'llmModel',
                                'name',
                                fn ($query) => $query->whereNotIn(
                                    'id',
                                    $this->getOwnerRecord()->modelEntitlements()->pluck('llm_model_id'),
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
