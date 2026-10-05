<?php

declare(strict_types=1);

namespace Larapilot\Http;

use Illuminate\Support\Facades\Route;
use Larapilot\Http\Controllers\MockupAssetsController;
use Larapilot\Http\Middleware\AddLarapilotSecurityHeaders;
use Larapilot\Http\Middleware\EnsureDashboardAuthorized;
use Larapilot\Services\ConfigService;

class MockupAssetsRouteRegistrar
{
    public static function register(): void
    {
        if (! app(ConfigService::class)->mockupAssetsBrowsable()) {
            return;
        }

        $prefix = trim((string) config('larapilot.mockup_assets_route.prefix', 'mockup-assets'), '/');

        // The assets a mockup loads follow the mockup: same sign-in, and a
        // reference page of a design system may be framed from this origin.
        $middleware = [
            ...(array) config('larapilot.mockup_assets_route.middleware', ['web']),
            AddLarapilotSecurityHeaders::class.':embed',
            EnsureDashboardAuthorized::class,
        ];

        Route::middleware($middleware)
            ->prefix($prefix)
            ->group(function (): void {
                Route::get('design-systems/{path}', MockupAssetsController::class)
                    ->where('path', '.*')
                    ->name('larapilot.mockup-assets.design-systems');
            });
    }
}
