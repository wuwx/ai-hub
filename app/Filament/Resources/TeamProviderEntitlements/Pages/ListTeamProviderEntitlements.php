<?php

namespace App\Filament\Resources\TeamProviderEntitlements\Pages;

use App\Filament\Resources\TeamProviderEntitlements\TeamProviderEntitlementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTeamProviderEntitlements extends ListRecords
{
    protected static string $resource = TeamProviderEntitlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
