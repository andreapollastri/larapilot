<?php

declare(strict_types=1);

namespace Larapilot\Http;

use Illuminate\Support\Facades\Route;
use Larapilot\Http\Controllers\MockupController;

class MockupRouteRegistrar
{
    public static function register(): void
    {
        if (! config('larapilot.enabled', true)) {
            return;
        }

        $controller = app(MockupController::class);

        if (! $controller->mockupsAreBrowsable()) {
            return;
        }

        $prefix = trim((string) config('larapilot.mockups_route.prefix', 'mockups'), '/');
        $middleware = config('larapilot.mockups_route.middleware', ['web']);

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
