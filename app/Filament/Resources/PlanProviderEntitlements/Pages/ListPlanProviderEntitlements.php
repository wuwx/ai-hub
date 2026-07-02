<?php

namespace App\Filament\Resources\PlanProviderEntitlements\Pages;

use App\Filament\Resources\PlanProviderEntitlements\PlanProviderEntitlementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlanProviderEntitlements extends ListRecords
{
    protected static string $resource = PlanProviderEntitlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
