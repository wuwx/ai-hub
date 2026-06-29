<?php

namespace App\Filament\Resources\RequestLogs\Pages;

use App\Filament\Resources\RequestLogs\RequestLogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRequestLog extends CreateRecord
{
    protected static string $resource = RequestLogResource::class;
}
