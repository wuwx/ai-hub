<?php

namespace App\Filament\Resources\RequestLogs\Pages;

use App\Filament\Resources\RequestLogs\RequestLogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRequestLog extends EditRecord
{
    protected static string $resource = RequestLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
