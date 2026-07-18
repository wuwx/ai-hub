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
        /** @var Plan $owner */
        $owner = $this->getOwnerRecord();

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
                                    $owner->modelEntitlements()->pluck('llm_model_id'),
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
