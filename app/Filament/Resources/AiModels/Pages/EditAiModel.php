<?php

namespace App\Filament\Resources\AiModels\Pages;

use App\Filament\Resources\AiModels\AiModelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAiModel extends EditRecord
{
    protected static string $resource = AiModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
