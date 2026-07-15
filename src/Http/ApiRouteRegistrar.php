<?php

declare(strict_types=1);

namespace Larapilot\Http;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Larapilot\Http\Controllers\ApiController;
use Larapilot\Services\ConfigService;

class ApiRouteRegistrar
{
    public static function register(): void
    {
        if (! app(ConfigService::class)->dashboardBrowsable()) {
            return;
        }

        $prefix = trim((string) config('larapilot.dashboard_route.prefix', 'larapilot'), '/');
        $middleware = config('larapilot.dashboard_route.middleware', ['web']);

        Route::middleware($middleware)
            ->withoutMiddleware([ValidateCsrfToken::class])
            ->prefix($prefix.'/api')
            ->group(function (): void {
                Route::get('/board', [ApiController::class, 'board'])
                    ->name('larapilot.api.board');

                Route::get('/specs', [ApiController::class, 'specs'])
                    ->name('larapilot.api.specs.index');

                Route::get('/specs/{code}', [ApiController::class, 'spec'])
                    ->where('code', '[A-Za-z0-9][A-Za-z0-9._-]*')
                    ->name('larapilot.api.specs.show');

                Route::post('/specs/{code}/comments', [ApiController::class, 'storeComment'])
                    ->where('code', '[A-Za-z0-9][A-Za-z0-9._-]*')
                    ->name('larapilot.api.specs.comments.store');

                Route::get('/prd', [ApiController::class, 'prd'])
                    ->name('larapilot.api.prd');

                Route::get('/openapi.json', [ApiController::class, 'openapi'])
                    ->name('larapilot.api.openapi');

                Route::get('/docs', [ApiController::class, 'docs'])
                    ->name('larapilot.api.docs');
            });
    }
}
