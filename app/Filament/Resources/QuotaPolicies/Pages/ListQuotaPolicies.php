<?php

namespace App\Filament\Resources\QuotaPolicies\Pages;

use App\Filament\Resources\QuotaPolicies\QuotaPolicyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQuotaPolicies extends ListRecords
{
    protected static string $resource = QuotaPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
