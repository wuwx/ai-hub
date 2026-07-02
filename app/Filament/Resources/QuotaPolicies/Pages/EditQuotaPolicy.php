<?php

namespace App\Filament\Resources\QuotaPolicies\Pages;

use App\Filament\Resources\QuotaPolicies\QuotaPolicyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditQuotaPolicy extends EditRecord
{
    protected static string $resource = QuotaPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
