<?php

declare(strict_types=1);

namespace Larapilot\Http;

use Illuminate\Support\Facades\Route;
use Larapilot\Http\Controllers\DashboardController;
use Larapilot\Http\Controllers\FileManagerController;
use Larapilot\Http\Middleware\AddLarapilotSecurityHeaders;
use Larapilot\Http\Middleware\EnsureDashboardAuthorized;
use Larapilot\Services\ConfigService;

class DashboardRouteRegistrar
{
    public static function register(): void
    {
        if (! app(ConfigService::class)->dashboardBrowsable()) {
            return;
        }

        $prefix = trim((string) config('larapilot.dashboard_route.prefix', 'larapilot'), '/');
        $base = (array) config('larapilot.dashboard_route.middleware', ['web']);

        $middleware = [
            ...$base,
            AddLarapilotSecurityHeaders::class,
            EnsureDashboardAuthorized::class,
        ];

        // The design viewer frames the presentation index, so that route runs
        // with same-origin framing allowed instead of the dashboard's DENY.
        $embedded = [
            ...$base,
            AddLarapilotSecurityHeaders::class.':embed',
            EnsureDashboardAuthorized::class,
        ];

        Route::middleware($middleware)
            ->prefix($prefix)
            ->group(function (): void {
                Route::get('/', [DashboardController::class, 'index'])
                    ->name('larapilot.dashboard.index');

                Route::get('/board.md', [DashboardController::class, 'boardDownload'])
                    ->name('larapilot.dashboard.board.download');

                Route::get('/prd', [DashboardController::class, 'prd'])
                    ->name('larapilot.dashboard.prd');

                Route::get('/prd/prd.md', [DashboardController::class, 'prdDownload'])
                    ->name('larapilot.dashboard.prd.download');

                Route::get('/prd/functional-summary.md', [DashboardController::class, 'functionalSummary'])
                    ->name('larapilot.dashboard.prd.summary');

                Route::get('/settings', [DashboardController::class, 'settings'])
                    ->name('larapilot.dashboard.settings');

                Route::get('/inception', [DashboardController::class, 'inception'])
                    ->name('larapilot.dashboard.inception');

                Route::get('/docs', [DashboardController::class, 'docs'])
                    ->name('larapilot.dashboard.docs');

                Route::get('/skills', [DashboardController::class, 'skills'])
                    ->name('larapilot.dashboard.skills');

                Route::get('/skills/guidelines/{id}', [DashboardController::class, 'guideline'])
                    ->where('id', '[a-z0-9][a-z0-9-]*')
                    ->name('larapilot.dashboard.skills.guideline');

                Route::get('/skills/{name}', [DashboardController::class, 'skill'])
                    ->where('name', '[A-Za-z0-9][A-Za-z0-9._-]*')
                    ->name('larapilot.dashboard.skill');

                Route::get('/skills/{name}/SKILL.md', [DashboardController::class, 'skillDownload'])
                    ->where('name', '[A-Za-z0-9][A-Za-z0-9._-]*')
                    ->name('larapilot.dashboard.skill.download');

                Route::get('/git', [DashboardController::class, 'git'])
                    ->name('larapilot.dashboard.git');

                Route::get('/plan', [DashboardController::class, 'plan'])
                    ->name('larapilot.dashboard.plan');

                Route::get('/usage', [DashboardController::class, 'usage'])
                    ->name('larapilot.dashboard.usage');

                Route::get('/usage/report.md', [DashboardController::class, 'usageReport'])
                    ->name('larapilot.dashboard.usage.report');

                Route::get('/economics', [DashboardController::class, 'economics'])
                    ->name('larapilot.dashboard.economics');

                Route::get('/economics/panel', [DashboardController::class, 'economicsPanel'])
                    ->name('larapilot.dashboard.economics.panel');

                Route::get('/economics/quote.md', [DashboardController::class, 'economicsQuote'])
                    ->name('larapilot.dashboard.economics.quote');

                Route::get('/economics/report.md', [DashboardController::class, 'economicsReport'])
                    ->name('larapilot.dashboard.economics.report');

                Route::get('/security', [DashboardController::class, 'security'])
                    ->name('larapilot.dashboard.security');

                Route::get('/security/aikido.md', [DashboardController::class, 'securityReport'])
                    ->name('larapilot.dashboard.security.report');

                Route::get('/errors', [DashboardController::class, 'errors'])
                    ->name('larapilot.dashboard.errors');

                Route::get('/errors/boogle.md', [DashboardController::class, 'errorsReport'])
                    ->name('larapilot.dashboard.errors.report');

                Route::get('/design', [DashboardController::class, 'design'])
                    ->name('larapilot.dashboard.design');

                Route::get('/design/package.zip', [DashboardController::class, 'designPackage'])
                    ->name('larapilot.dashboard.design.package');

                Route::post('/design/mockups/{code}/style', [DashboardController::class, 'chooseMockupStyle'])
                    ->where('code', '[A-Za-z0-9][A-Za-z0-9._-]*')
                    ->name('larapilot.dashboard.design.style');

                Route::get('/specs/{code}', [DashboardController::class, 'spec'])
                    ->where('code', '[A-Za-z0-9][A-Za-z0-9._-]*')
                    ->name('larapilot.dashboard.spec');

                Route::get('/specs/{code}/spec.md', [DashboardController::class, 'specDownload'])
                    ->where('code', '[A-Za-z0-9][A-Za-z0-9._-]*')
                    ->name('larapilot.dashboard.spec.download');

                Route::post('/specs/{code}/comments', [DashboardController::class, 'storeComment'])
                    ->where('code', '[A-Za-z0-9][A-Za-z0-9._-]*')
                    ->name('larapilot.dashboard.spec.comments.store');

                self::registerFileManager();
            });

        Route::middleware($embedded)
            ->prefix($prefix)
            ->group(function (): void {
                Route::get('/design/presentation', [DashboardController::class, 'designPresentation'])
                    ->name('larapilot.dashboard.design.presentation');
            });
    }

    /**
     * The material folders under `.larapilot/`, and the project itself.
     * Only the known roots match, so `raw` and the action names can never
     * be read as a folder. The project is read only: the routes that write
     * do not know it.
     */
    protected static function registerFileManager(): void
    {
        $roots = 'brand|client-materials|design-systems|legacy|skills';
        $readable = $roots.'|project';

        Route::get('/files', [FileManagerController::class, 'index'])
            ->name('larapilot.dashboard.files');

        Route::get('/files/raw/{root}/{path}', [FileManagerController::class, 'raw'])
            ->where('root', $readable)
            ->where('path', '.*')
            ->name('larapilot.dashboard.files.raw');

        Route::post('/files/{root}/upload', [FileManagerController::class, 'upload'])
            ->where('root', $roots)
            ->name('larapilot.dashboard.files.upload');

        Route::post('/files/{root}/folder', [FileManagerController::class, 'folder'])
            ->where('root', $roots)
            ->name('larapilot.dashboard.files.folder');

        Route::post('/files/{root}/rename', [FileManagerController::class, 'rename'])
            ->where('root', $roots)
            ->name('larapilot.dashboard.files.rename');

        Route::post('/files/{root}/delete', [FileManagerController::class, 'destroy'])
            ->where('root', $roots)
            ->name('larapilot.dashboard.files.delete');

        Route::get('/files/{root}/{path?}', [FileManagerController::class, 'browse'])
            ->where('root', $readable)
            ->where('path', '.*')
            ->name('larapilot.dashboard.files.browse');
    }
}
