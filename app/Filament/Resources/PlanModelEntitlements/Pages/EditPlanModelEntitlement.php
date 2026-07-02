<?php

namespace App\Filament\Resources\PlanModelEntitlements\Pages;

use App\Filament\Resources\PlanModelEntitlements\PlanModelEntitlementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlanModelEntitlement extends EditRecord
{
    protected static string $resource = PlanModelEntitlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
