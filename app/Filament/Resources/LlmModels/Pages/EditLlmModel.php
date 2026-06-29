<?php

namespace App\Filament\Resources\LlmModels\Pages;

use App\Filament\Resources\LlmModels\LlmModelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLlmModel extends EditRecord
{
    protected static string $resource = LlmModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
