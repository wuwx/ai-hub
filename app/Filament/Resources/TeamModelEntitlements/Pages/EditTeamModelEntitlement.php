<?php

namespace App\Filament\Resources\TeamModelEntitlements\Pages;

use App\Filament\Resources\TeamModelEntitlements\TeamModelEntitlementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTeamModelEntitlement extends EditRecord
{
    protected static string $resource = TeamModelEntitlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
