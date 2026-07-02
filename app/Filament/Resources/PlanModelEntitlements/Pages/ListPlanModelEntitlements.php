<?php

namespace App\Filament\Resources\PlanModelEntitlements\Pages;

use App\Filament\Resources\PlanModelEntitlements\PlanModelEntitlementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlanModelEntitlements extends ListRecords
{
    protected static string $resource = PlanModelEntitlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
