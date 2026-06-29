<?php

namespace App\Filament\Resources\LlmProviders\Pages;

use App\Filament\Resources\LlmProviders\LlmProviderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLlmProviders extends ListRecords
{
    protected static string $resource = LlmProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
