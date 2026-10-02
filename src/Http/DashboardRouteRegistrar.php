<?php

declare(strict_types=1);

namespace Larapilot\Http;

use Illuminate\Support\Facades\Route;
use Larapilot\Http\Controllers\DashboardController;
use Larapilot\Http\Controllers\DatabaseViewerController;
use Larapilot\Http\Controllers\FileManagerController;
use Larapilot\Http\Controllers\LaravelViewerController;
use Larapilot\Http\Controllers\LogViewerController;
use Larapilot\Http\Controllers\StackController;
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

                Route::get('/epics.md', [DashboardController::class, 'epicsDownload'])
                    ->name('larapilot.dashboard.board.epics');

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

                Route::get('/plan/plan.md', [DashboardController::class, 'planDownload'])
                    ->name('larapilot.dashboard.plan.download');

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

                Route::get('/security/register.md', [DashboardController::class, 'securityRegister'])
                    ->name('larapilot.dashboard.security.register');

                Route::post('/security/repository', [DashboardController::class, 'chooseSecurityRepository'])
                    ->name('larapilot.dashboard.security.repository');

                Route::get('/security/checkpoint', [StackController::class, 'checkpoint'])
                    ->name('larapilot.dashboard.security.checkpoint');

                Route::post('/security/checkpoint/scan', [StackController::class, 'runCheckpoint'])
                    ->name('larapilot.dashboard.security.checkpoint.scan');

                Route::get('/security/checkpoint.md', [StackController::class, 'checkpointReport'])
                    ->name('larapilot.dashboard.security.checkpoint.report');

                Route::get('/sbom', [StackController::class, 'sbom'])
                    ->name('larapilot.dashboard.sbom');

                Route::get('/sbom/sbom.md', [StackController::class, 'sbomMarkdown'])
                    ->name('larapilot.dashboard.sbom.download');

                Route::get('/sbom/sbom.cdx.json', [StackController::class, 'sbomCyclonedx'])
                    ->name('larapilot.dashboard.sbom.cyclonedx');

                Route::get('/sbom/vendor-audit.md', [StackController::class, 'vendorAuditReport'])
                    ->name('larapilot.dashboard.sbom.report');

                Route::post('/sbom/audit', [StackController::class, 'runVendorAudit'])
                    ->name('larapilot.dashboard.sbom.audit');

                Route::get('/about', [StackController::class, 'about'])
                    ->name('larapilot.dashboard.about');

                Route::get('/errors', [DashboardController::class, 'errors'])
                    ->name('larapilot.dashboard.errors');

                Route::get('/errors/errors.md', [DashboardController::class, 'errorsReport'])
                    ->name('larapilot.dashboard.errors.report');
                // The address the report had when Boogle was the only tracker.
                Route::get('/errors/boogle.md', [DashboardController::class, 'errorsReport']);

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

                Route::get('/database', [DatabaseViewerController::class, 'index'])
                    ->name('larapilot.dashboard.database');

                Route::get('/database.sql', [DatabaseViewerController::class, 'dump'])
                    ->name('larapilot.dashboard.database.dump');

                Route::get('/database-diagram.pdf', [DatabaseViewerController::class, 'diagram'])
                    ->name('larapilot.dashboard.database.diagram');

                // A table is found by name in what the connection lists, so
                // any name it holds is allowed here and nothing else is read.
                Route::get('/database/{table}', [DatabaseViewerController::class, 'table'])
                    ->where('table', '.+')
                    ->name('larapilot.dashboard.database.table');

                Route::get('/logs', [LogViewerController::class, 'index'])
                    ->name('larapilot.dashboard.logs');

                // A log is found by name in what the folder lists, so any
                // name it holds is allowed here and nothing else is opened.
                Route::get('/logs/{file}', [LogViewerController::class, 'show'])
                    ->where('file', '.+')
                    ->name('larapilot.dashboard.logs.file');

                Route::get('/laravel', [LaravelViewerController::class, 'index'])
                    ->name('larapilot.dashboard.laravel');

                Route::get('/laravel/schedule', [LaravelViewerController::class, 'schedule'])
                    ->name('larapilot.dashboard.laravel.schedule');

                Route::get('/laravel/queue', [LaravelViewerController::class, 'queue'])
                    ->name('larapilot.dashboard.laravel.queue');

                Route::get('/laravel/mail', [LaravelViewerController::class, 'mail'])
                    ->name('larapilot.dashboard.laravel.mail');

                Route::post('/laravel/mail/clear', [LaravelViewerController::class, 'clearMail'])
                    ->name('larapilot.dashboard.laravel.mail.clear');

                // A mail is found by id in what the folder lists; an id is
                // digits and hex, so it is never read as a path.
                Route::get('/laravel/mail/{id}', [LaravelViewerController::class, 'message'])
                    ->where('id', '[0-9a-f]{26}')
                    ->name('larapilot.dashboard.laravel.mail.message');

                Route::get('/laravel/dumps', [LaravelViewerController::class, 'dumps'])
                    ->name('larapilot.dashboard.laravel.dumps');

                Route::post('/laravel/dumps/clear', [LaravelViewerController::class, 'clearDumps'])
                    ->name('larapilot.dashboard.laravel.dumps.clear');
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
