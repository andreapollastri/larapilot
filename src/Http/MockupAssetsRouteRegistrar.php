<?php

declare(strict_types=1);

namespace Larapilot\Http;

use Illuminate\Support\Facades\Route;
use Larapilot\Http\Controllers\MockupAssetsController;
use Larapilot\Services\ConfigService;

class MockupAssetsRouteRegistrar
{
    public static function register(): void
    {
        if (! app(ConfigService::class)->mockupsBrowsable()) {
            return;
        }

        $prefix = trim((string) config('larapilot.mockup_assets_route.prefix', 'mockup-assets'), '/');
        $middleware = config('larapilot.mockup_assets_route.middleware', ['web']);

        Route::middleware($middleware)
            ->prefix($prefix)
            ->group(function (): void {
                Route::get('design-systems/{path}', MockupAssetsController::class)
                    ->where('path', '.*')
                    ->name('larapilot.mockup-assets.design-systems');
            });
    }
}
