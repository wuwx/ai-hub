<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\PrometheusServiceProvider;

return [
    AppServiceProvider::class,
    PrometheusServiceProvider::class,
    AdminPanelProvider::class,
    FortifyServiceProvider::class,
];
