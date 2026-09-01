<?php

declare(strict_types=1);

namespace Larapilot;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Larapilot\Console\Commands\AzureDevopsStatusCommand;
use Larapilot\Console\Commands\BackstageExportCommand;
use Larapilot\Console\Commands\BitbucketStatusCommand;
use Larapilot\Console\Commands\ChoicesSetCommand;
use Larapilot\Console\Commands\CodeHistoryLogCommand;
use Larapilot\Console\Commands\CodeHistoryShowCommand;
use Larapilot\Console\Commands\ConfigShowCommand;
use Larapilot\Console\Commands\DashboardUserCommand;
use Larapilot\Console\Commands\DecisionCheckCommand;
use Larapilot\Console\Commands\DecisionLogCommand;
use Larapilot\Console\Commands\DiagnosticsCommand;
use Larapilot\Console\Commands\DoctorCommand;
use Larapilot\Console\Commands\FrontendScanCommand;
use Larapilot\Console\Commands\FrontendSetCommand;
use Larapilot\Console\Commands\GithubStatusCommand;
use Larapilot\Console\Commands\GitlabStatusCommand;
use Larapilot\Console\Commands\InstallCommand;
use Larapilot\Console\Commands\MetricsCommand;
use Larapilot\Console\Commands\NotifyCommand;
use Larapilot\Console\Commands\PrdWriteCommand;
use Larapilot\Console\Commands\QualityCommand;
use Larapilot\Console\Commands\ScheduleSetCommand;
use Larapilot\Console\Commands\SettingsSetCommand;
use Larapilot\Console\Commands\SpecAddCommand;
use Larapilot\Console\Commands\SpecApproveCommand;
use Larapilot\Console\Commands\SpecCommentCommand;
use Larapilot\Console\Commands\SpecDeleteCommand;
use Larapilot\Console\Commands\SpecListCommand;
use Larapilot\Console\Commands\SpecNextCommand;
use Larapilot\Console\Commands\SpecPlanCommand;
use Larapilot\Console\Commands\SpecRequestChangesCommand;
use Larapilot\Console\Commands\SpecReviewCommand;
use Larapilot\Console\Commands\SpecShowCommand;
use Larapilot\Console\Commands\SpecStartCommand;
use Larapilot\Console\Commands\TaskDoneCommand;
use Larapilot\Console\Commands\TrackerPullCommand;
use Larapilot\Console\Commands\TrackerPushCommand;
use Larapilot\Console\Commands\TrackerStatusCommand;
use Larapilot\Console\Commands\UpdateCommand;
use Larapilot\Console\Commands\UsageLogCommand;
use Larapilot\Console\Commands\UsageReportCommand;
use Larapilot\Console\Commands\ValidatePlanCommand;
use Larapilot\Console\Commands\ValidatePrdCommand;
use Larapilot\Console\Commands\ValidateSpecCommand;
use Larapilot\Console\Commands\VpsProvisionCommand;
use Larapilot\Http\ApiRouteRegistrar;
use Larapilot\Http\DashboardRouteRegistrar;
use Larapilot\Http\MockupAssetsRouteRegistrar;
use Larapilot\Http\MockupRouteRegistrar;
use Larapilot\Mcp\LarapilotServer;
use Larapilot\Services\ApiAuditService;
use Larapilot\Services\ApiService;
use Larapilot\Services\AzureDevopsService;
use Larapilot\Services\BackstageService;
use Larapilot\Services\BitbucketService;
use Larapilot\Services\CodeHistoryService;
use Larapilot\Services\CodeQualityService;
use Larapilot\Services\CompanionService;
use Larapilot\Services\ConfigService;
use Larapilot\Services\DashboardService;
use Larapilot\Services\DecisionService;
use Larapilot\Services\DiagnosticsService;
use Larapilot\Services\FrontendService;
use Larapilot\Services\GithubService;
use Larapilot\Services\GitlabService;
use Larapilot\Services\GitService;
use Larapilot\Services\InternalFeedbackService;
use Larapilot\Services\MetricsService;
use Larapilot\Services\MockupService;
use Larapilot\Services\NotifyService;
use Larapilot\Services\OpenApiService;
use Larapilot\Services\PlanService;
use Larapilot\Services\PrdService;
use Larapilot\Services\SpecService;
use Larapilot\Services\Tracker\TrackerLinkStore;
use Larapilot\Services\Tracker\TrackerManager;
use Larapilot\Services\TrackerService;
use Larapilot\Services\ValidationService;
use Larapilot\Support\MockupAssetResolver;
use Larapilot\Support\MockupCssProcessor;
use Larapilot\Support\MockupHtmlProcessor;
use Laravel\Mcp\Facades\Mcp;

class LarapilotServiceProvider extends ServiceProvider
{
    public const VERSION = '2.5.1';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/larapilot.php', 'larapilot');

        $this->app->singleton(ConfigService::class);
        $this->app->singleton(CodeQualityService::class);
        $this->app->singleton(DecisionService::class);
        $this->app->singleton(CodeHistoryService::class);
        $this->app->singleton(CompanionService::class);
        $this->app->singleton(FrontendService::class);
        $this->app->singleton(BackstageService::class);
        $this->app->singleton(DiagnosticsService::class);
        $this->app->singleton(GitService::class);
        $this->app->singleton(GithubService::class);
        $this->app->singleton(GitlabService::class);
        $this->app->singleton(BitbucketService::class);
        $this->app->singleton(AzureDevopsService::class);
        $this->app->singleton(NotifyService::class);
        $this->app->singleton(PrdService::class);
        $this->app->singleton(SpecService::class);
        $this->app->singleton(PlanService::class);
        $this->app->singleton(MockupAssetResolver::class);
        $this->app->singleton(MockupHtmlProcessor::class);
        $this->app->singleton(MockupCssProcessor::class);
        $this->app->singleton(MockupService::class);
        $this->app->singleton(InternalFeedbackService::class);
        $this->app->singleton(DashboardService::class);
        $this->app->singleton(ApiService::class);
        $this->app->singleton(ApiAuditService::class);
        $this->app->singleton(MetricsService::class);
        $this->app->singleton(OpenApiService::class);
        $this->app->singleton(ValidationService::class);
        $this->app->singleton(TrackerManager::class);
        $this->app->singleton(TrackerLinkStore::class);
        $this->app->singleton(TrackerService::class);
    }

    public function boot(): void
    {
        // Commands and publishing stay available even when larapilot is
        // disabled, so larapilot:doctor can diagnose a disabled install.
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                UpdateCommand::class,
                DoctorCommand::class,
                DiagnosticsCommand::class,
                FrontendSetCommand::class,
                FrontendScanCommand::class,
                BackstageExportCommand::class,
                ConfigShowCommand::class,
                SettingsSetCommand::class,
                DashboardUserCommand::class,
                NotifyCommand::class,
                GithubStatusCommand::class,
                GitlabStatusCommand::class,
                BitbucketStatusCommand::class,
                AzureDevopsStatusCommand::class,
                PrdWriteCommand::class,
                ValidatePrdCommand::class,
                SpecListCommand::class,
                SpecAddCommand::class,
                SpecShowCommand::class,
                SpecNextCommand::class,
                SpecPlanCommand::class,
                SpecStartCommand::class,
                SpecReviewCommand::class,
                SpecCommentCommand::class,
                SpecRequestChangesCommand::class,
                TaskDoneCommand::class,
                MetricsCommand::class,
                UsageLogCommand::class,
                UsageReportCommand::class,
                ScheduleSetCommand::class,
                ChoicesSetCommand::class,
                DecisionLogCommand::class,
                DecisionCheckCommand::class,
                CodeHistoryLogCommand::class,
                CodeHistoryShowCommand::class,
                QualityCommand::class,
                ValidateSpecCommand::class,
                ValidatePlanCommand::class,
                SpecApproveCommand::class,
                SpecDeleteCommand::class,
                TrackerStatusCommand::class,
                TrackerPushCommand::class,
                TrackerPullCommand::class,
                VpsProvisionCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/larapilot.php' => config_path('larapilot.php'),
            ], 'larapilot-config');
        }

        if (! config('larapilot.enabled', true)) {
            return;
        }

        Mcp::local('larapilot', LarapilotServer::class);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'larapilot');

        $this->registerApiRateLimiter();

        MockupRouteRegistrar::register();
        MockupAssetsRouteRegistrar::register();
        DashboardRouteRegistrar::register();
        ApiRouteRegistrar::register();
    }

    /**
     * Per-IP rate limit for `/larapilot/api/*`, resolved from
     * `larapilot.api.rate_limit` ("max,minutes") at request time. An empty
     * value or a non-positive max means no limit.
     */
    protected function registerApiRateLimiter(): void
    {
        RateLimiter::for('larapilot-api', function (Request $request): Limit {
            $spec = trim((string) config('larapilot.api.rate_limit', '120,1'));

            if ($spec === '') {
                return Limit::none();
            }

            [$max, $minutes] = array_pad(explode(',', $spec, 2), 2, '1');
            $max = (int) trim($max);

            if ($max <= 0) {
                return Limit::none();
            }

            return Limit::perMinutes(max(1, (int) trim($minutes)), $max)
                ->by((string) $request->ip());
        });
    }
}
