<?php

namespace App\Filament\Resources\TeamQuotaPolicies\Pages;

use App\Filament\Resources\TeamQuotaPolicies\TeamQuotaPolicyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTeamQuotaPolicies extends ListRecords
{
    protected static string $resource = TeamQuotaPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
