<?php

declare(strict_types=1);

namespace Larapilot\Http;

use Illuminate\Support\Facades\Route;
use Larapilot\Http\Controllers\MockupController;
use Larapilot\Http\Middleware\AddLarapilotSecurityHeaders;
use Larapilot\Http\Middleware\EnsureDashboardAuthorized;
use Larapilot\Services\ConfigService;

class MockupRouteRegistrar
{
    public static function register(): void
    {
        if (! app(ConfigService::class)->mockupsBrowsable()) {
            return;
        }

        $prefix = trim((string) config('larapilot.mockups_route.prefix', 'mockups'), '/');

        // A mockup is a page of the project shown to whoever may see the
        // dashboard: the same sign-in, when it is on. The Design page frames
        // it, so framing from this origin stays allowed.
        $middleware = [
            ...(array) config('larapilot.mockups_route.middleware', ['web']),
            AddLarapilotSecurityHeaders::class.':embed',
            EnsureDashboardAuthorized::class,
        ];

        Route::middleware($middleware)
            ->prefix($prefix)
            ->group(function (): void {
                Route::get('{spec}/{path?}', MockupController::class)
                    ->where('spec', '[A-Za-z0-9][A-Za-z0-9._-]*')
                    ->where('path', '.*')
                    ->name('larapilot.mockups.show');
            });
    }
}
