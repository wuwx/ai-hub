<?php

namespace App\Filament\Resources\TeamQuotaPolicies\Pages;

use App\Filament\Resources\TeamQuotaPolicies\TeamQuotaPolicyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTeamQuotaPolicy extends EditRecord
{
    protected static string $resource = TeamQuotaPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
