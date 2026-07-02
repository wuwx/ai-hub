<?php

namespace App\Filament\Resources\PlanProviderEntitlements\Pages;

use App\Filament\Resources\PlanProviderEntitlements\PlanProviderEntitlementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlanProviderEntitlement extends EditRecord
{
    protected static string $resource = PlanProviderEntitlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
