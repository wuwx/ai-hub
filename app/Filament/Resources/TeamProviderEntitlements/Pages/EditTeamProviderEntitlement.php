<?php

namespace App\Filament\Resources\TeamProviderEntitlements\Pages;

use App\Filament\Resources\TeamProviderEntitlements\TeamProviderEntitlementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTeamProviderEntitlement extends EditRecord
{
    protected static string $resource = TeamProviderEntitlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
