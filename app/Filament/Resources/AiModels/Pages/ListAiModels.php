<?php

namespace App\Filament\Resources\AiModels\Pages;

use App\Filament\Resources\AiModels\AiModelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAiModels extends ListRecords
{
    protected static string $resource = AiModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
