<?php

namespace App\Filament\Resources\TeamModelEntitlements\Pages;

use App\Filament\Resources\TeamModelEntitlements\TeamModelEntitlementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTeamModelEntitlements extends ListRecords
{
    protected static string $resource = TeamModelEntitlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
