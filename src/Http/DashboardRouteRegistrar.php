<?php

declare(strict_types=1);

namespace Larapilot\Http;

use Illuminate\Support\Facades\Route;
use Larapilot\Http\Controllers\DashboardController;
use Larapilot\Services\ConfigService;

class DashboardRouteRegistrar
{
    public static function register(): void
    {
        if (! app(ConfigService::class)->dashboardBrowsable()) {
            return;
        }

        $prefix = trim((string) config('larapilot.dashboard_route.prefix', 'larapilot'), '/');
        $middleware = config('larapilot.dashboard_route.middleware', ['web']);

        Route::middleware($middleware)
            ->prefix($prefix)
            ->group(function (): void {
                Route::get('/', [DashboardController::class, 'index'])
                    ->name('larapilot.dashboard.index');

                Route::get('/prd', [DashboardController::class, 'prd'])
                    ->name('larapilot.dashboard.prd');

                Route::get('/specs/{code}', [DashboardController::class, 'spec'])
                    ->where('code', '[A-Za-z0-9][A-Za-z0-9._-]*')
                    ->name('larapilot.dashboard.spec');
            });
    }
}
