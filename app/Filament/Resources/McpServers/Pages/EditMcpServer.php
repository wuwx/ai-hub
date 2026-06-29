<?php

namespace App\Filament\Resources\McpServers\Pages;

use App\Filament\Resources\McpServers\McpServerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMcpServer extends EditRecord
{
    protected static string $resource = McpServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
