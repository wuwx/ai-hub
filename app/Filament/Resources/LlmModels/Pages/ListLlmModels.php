<?php

namespace App\Filament\Resources\LlmModels\Pages;

use App\Filament\Resources\LlmModels\LlmModelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLlmModels extends ListRecords
{
    protected static string $resource = LlmModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
