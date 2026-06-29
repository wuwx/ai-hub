<?php

namespace App\Filament\Resources\BillingInvoices\Pages;

use App\Filament\Resources\BillingInvoices\BillingInvoiceResource;
use Filament\Resources\Pages\ListRecords;

class ListBillingInvoices extends ListRecords
{
    protected static string $resource = BillingInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
