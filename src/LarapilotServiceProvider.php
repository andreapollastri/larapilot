<?php

declare(strict_types=1);

namespace Larapilot;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Larapilot\Console\Commands\AikidoIssuesCommand;
use Larapilot\Console\Commands\AikidoLinkCommand;
use Larapilot\Console\Commands\AikidoPlanCommand;
use Larapilot\Console\Commands\AikidoPushCommand;
use Larapilot\Console\Commands\AikidoRegisterCommand;
use Larapilot\Console\Commands\AikidoReposCommand;
use Larapilot\Console\Commands\AikidoScanCommand;
use Larapilot\Console\Commands\AikidoStatusCommand;
use Larapilot\Console\Commands\AzureDevopsStatusCommand;
use Larapilot\Console\Commands\BackstageExportCommand;
use Larapilot\Console\Commands\BitbucketStatusCommand;
use Larapilot\Console\Commands\CheckpointScanCommand;
use Larapilot\Console\Commands\ChoicesSetCommand;
use Larapilot\Console\Commands\CodeHistoryLogCommand;
use Larapilot\Console\Commands\CodeHistoryShowCommand;
use Larapilot\Console\Commands\ConfigShowCommand;
use Larapilot\Console\Commands\ContextCommand;
use Larapilot\Console\Commands\CustomSkillAddCommand;
use Larapilot\Console\Commands\CustomSkillListCommand;
use Larapilot\Console\Commands\DashboardUserCommand;
use Larapilot\Console\Commands\DecisionCheckCommand;
use Larapilot\Console\Commands\DecisionLogCommand;
use Larapilot\Console\Commands\DiagnosticsCommand;
use Larapilot\Console\Commands\DoctorCommand;
use Larapilot\Console\Commands\EconomicsMarketWriteCommand;
use Larapilot\Console\Commands\EconomicsQuoteWriteCommand;
use Larapilot\Console\Commands\EconomicsSetCommand;
use Larapilot\Console\Commands\EconomicsShowCommand;
use Larapilot\Console\Commands\ErrorsLinkCommand;
use Larapilot\Console\Commands\ErrorsListCommand;
use Larapilot\Console\Commands\ErrorsPlanCommand;
use Larapilot\Console\Commands\ErrorsResolveCommand;
use Larapilot\Console\Commands\ErrorsStatusCommand;
use Larapilot\Console\Commands\FrontendBriefCommand;
use Larapilot\Console\Commands\FrontendRulesCommand;
use Larapilot\Console\Commands\FrontendScanCommand;
use Larapilot\Console\Commands\FrontendSetCommand;
use Larapilot\Console\Commands\GithubStatusCommand;
use Larapilot\Console\Commands\GitlabStatusCommand;
use Larapilot\Console\Commands\HookListCommand;
use Larapilot\Console\Commands\HookRunCommand;
use Larapilot\Console\Commands\InstallCommand;
use Larapilot\Console\Commands\MetricsCommand;
use Larapilot\Console\Commands\MockupChooseStyleCommand;
use Larapilot\Console\Commands\NotifyCommand;
use Larapilot\Console\Commands\PrdImpactCommand;
use Larapilot\Console\Commands\PrdShowCommand;
use Larapilot\Console\Commands\PrdWriteCommand;
use Larapilot\Console\Commands\QualityCommand;
use Larapilot\Console\Commands\ReleaseAddCommand;
use Larapilot\Console\Commands\ReleaseCutCommand;
use Larapilot\Console\Commands\ReleaseFeatureCommand;
use Larapilot\Console\Commands\ReleaseImportCommand;
use Larapilot\Console\Commands\ReleaseListCommand;
use Larapilot\Console\Commands\ReleaseSetCommand;
use Larapilot\Console\Commands\ReleaseShipCommand;
use Larapilot\Console\Commands\ReleaseSyncCommand;
use Larapilot\Console\Commands\SbomCommand;
use Larapilot\Console\Commands\ScheduleApplyCommand;
use Larapilot\Console\Commands\ScheduleSetCommand;
use Larapilot\Console\Commands\ScheduleShowCommand;
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
use Larapilot\Console\Commands\StackCommand;
use Larapilot\Console\Commands\TaskDoneCommand;
use Larapilot\Console\Commands\TrackerPullCommand;
use Larapilot\Console\Commands\TrackerPushCommand;
use Larapilot\Console\Commands\TrackerStatusCommand;
use Larapilot\Console\Commands\UpdateCommand;
use Larapilot\Console\Commands\UpgradeCheckCommand;
use Larapilot\Console\Commands\UsageLogCommand;
use Larapilot\Console\Commands\UsageReportCommand;
use Larapilot\Console\Commands\ValidatePlanCommand;
use Larapilot\Console\Commands\ValidatePrdCommand;
use Larapilot\Console\Commands\ValidateSpecCommand;
use Larapilot\Console\Commands\VendorAuditCommand;
use Larapilot\Console\Commands\VendorLinkCommand;
use Larapilot\Http\ApiRouteRegistrar;
use Larapilot\Http\DashboardRouteRegistrar;
use Larapilot\Http\MockupAssetsRouteRegistrar;
use Larapilot\Http\MockupRouteRegistrar;
use Larapilot\Mcp\LarapilotServer;
use Larapilot\Services\AgentGuidelineService;
use Larapilot\Services\Aikido\AikidoClient;
use Larapilot\Services\Aikido\AikidoLedger;
use Larapilot\Services\AikidoRegisterWriter;
use Larapilot\Services\AikidoService;
use Larapilot\Services\ApiAuditService;
use Larapilot\Services\ApiService;
use Larapilot\Services\AzureDevopsService;
use Larapilot\Services\BackstageService;
use Larapilot\Services\BitbucketService;
use Larapilot\Services\Boogle\BoogleClient;
use Larapilot\Services\Boogle\BoogleLedger;
use Larapilot\Services\BoogleService;
use Larapilot\Services\CodeHistoryService;
use Larapilot\Services\CodeQualityService;
use Larapilot\Services\CompanionService;
use Larapilot\Services\ConfigService;
use Larapilot\Services\ContextService;
use Larapilot\Services\CustomSkillService;
use Larapilot\Services\DashboardExportService;
use Larapilot\Services\DashboardService;
use Larapilot\Services\DecisionService;
use Larapilot\Services\DiagnosticsService;
use Larapilot\Services\EconomicsMarketService;
use Larapilot\Services\EconomicsQuoteWriter;
use Larapilot\Services\EconomicsService;
use Larapilot\Services\Errors\ErrorDataHelper;
use Larapilot\Services\Errors\ErrorTrackerManager;
use Larapilot\Services\FileManagerService;
use Larapilot\Services\FrontendBriefService;
use Larapilot\Services\FrontendService;
use Larapilot\Services\GitGraphService;
use Larapilot\Services\GithubService;
use Larapilot\Services\GitlabService;
use Larapilot\Services\GitService;
use Larapilot\Services\InternalFeedbackService;
use Larapilot\Services\MetricsService;
use Larapilot\Services\MockupPackageService;
use Larapilot\Services\MockupService;
use Larapilot\Services\NotifyService;
use Larapilot\Services\OpenApiService;
use Larapilot\Services\PlanService;
use Larapilot\Services\PrdService;
use Larapilot\Services\ReleaseFlowService;
use Larapilot\Services\ReleaseService;
use Larapilot\Services\SkillLibraryService;
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
    public const VERSION = '5.0.0';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/larapilot.php', 'larapilot');

        $this->app->singleton(ConfigService::class);
        $this->app->singleton(ContextService::class);
        $this->app->singleton(CodeQualityService::class);
        $this->app->singleton(DecisionService::class);
        $this->app->singleton(CodeHistoryService::class);
        $this->app->singleton(CompanionService::class);
        $this->app->singleton(FrontendService::class);
        $this->app->singleton(FrontendBriefService::class);
        $this->app->singleton(BackstageService::class);
        $this->app->singleton(DiagnosticsService::class);
        $this->app->singleton(GitService::class);
        $this->app->singleton(GitGraphService::class);
        $this->app->singleton(SkillLibraryService::class);
        $this->app->singleton(AgentGuidelineService::class);
        $this->app->singleton(GithubService::class);
        $this->app->singleton(GitlabService::class);
        $this->app->singleton(BitbucketService::class);
        $this->app->singleton(AzureDevopsService::class);
        $this->app->singleton(AikidoClient::class);
        $this->app->singleton(AikidoLedger::class);
        $this->app->singleton(AikidoService::class);
        $this->app->singleton(AikidoRegisterWriter::class);
        $this->app->singleton(BoogleClient::class);
        $this->app->singleton(BoogleLedger::class);
        $this->app->singleton(ErrorDataHelper::class);
        $this->app->singleton(ErrorTrackerManager::class);
        $this->app->singleton(BoogleService::class);
        $this->app->singleton(NotifyService::class);
        $this->app->singleton(PrdService::class);
        $this->app->singleton(SpecService::class);
        $this->app->singleton(PlanService::class);
        $this->app->singleton(MockupAssetResolver::class);
        $this->app->singleton(MockupHtmlProcessor::class);
        $this->app->singleton(MockupCssProcessor::class);
        $this->app->singleton(MockupService::class);
        $this->app->singleton(MockupPackageService::class);
        $this->app->singleton(InternalFeedbackService::class);
        $this->app->singleton(DashboardService::class);
        $this->app->singleton(DashboardExportService::class);
        $this->app->singleton(ApiService::class);
        $this->app->singleton(ApiAuditService::class);
        $this->app->singleton(MetricsService::class);
        $this->app->singleton(OpenApiService::class);
        $this->app->singleton(ValidationService::class);
        $this->app->singleton(TrackerManager::class);
        $this->app->singleton(TrackerLinkStore::class);
        $this->app->singleton(TrackerService::class);
        $this->app->singleton(ReleaseService::class);
        $this->app->singleton(ReleaseFlowService::class);
        $this->app->singleton(CustomSkillService::class);
        $this->app->singleton(FileManagerService::class);
        $this->app->singleton(EconomicsMarketService::class);
        $this->app->singleton(EconomicsQuoteWriter::class);
        $this->app->singleton(EconomicsService::class);
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
                FrontendRulesCommand::class,
                FrontendBriefCommand::class,
                BackstageExportCommand::class,
                ConfigShowCommand::class,
                ContextCommand::class,
                SettingsSetCommand::class,
                DashboardUserCommand::class,
                HookListCommand::class,
                HookRunCommand::class,
                NotifyCommand::class,
                GithubStatusCommand::class,
                GitlabStatusCommand::class,
                BitbucketStatusCommand::class,
                AzureDevopsStatusCommand::class,
                AikidoStatusCommand::class,
                AikidoIssuesCommand::class,
                AikidoPlanCommand::class,
                AikidoLinkCommand::class,
                AikidoScanCommand::class,
                AikidoPushCommand::class,
                AikidoReposCommand::class,
                AikidoRegisterCommand::class,
                CheckpointScanCommand::class,
                SbomCommand::class,
                VendorAuditCommand::class,
                VendorLinkCommand::class,
                StackCommand::class,
                UpgradeCheckCommand::class,
                ErrorsStatusCommand::class,
                ErrorsListCommand::class,
                ErrorsPlanCommand::class,
                ErrorsLinkCommand::class,
                ErrorsResolveCommand::class,
                PrdWriteCommand::class,
                ValidatePrdCommand::class,
                PrdImpactCommand::class,
                PrdShowCommand::class,
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
                MockupChooseStyleCommand::class,
                UsageLogCommand::class,
                UsageReportCommand::class,
                ScheduleSetCommand::class,
                ScheduleShowCommand::class,
                ScheduleApplyCommand::class,
                ChoicesSetCommand::class,
                DecisionLogCommand::class,
                DecisionCheckCommand::class,
                CodeHistoryLogCommand::class,
                CodeHistoryShowCommand::class,
                ReleaseListCommand::class,
                ReleaseAddCommand::class,
                ReleaseSetCommand::class,
                ReleaseImportCommand::class,
                ReleaseCutCommand::class,
                ReleaseFeatureCommand::class,
                ReleaseSyncCommand::class,
                ReleaseShipCommand::class,
                CustomSkillListCommand::class,
                CustomSkillAddCommand::class,
                EconomicsMarketWriteCommand::class,
                EconomicsQuoteWriteCommand::class,
                EconomicsSetCommand::class,
                EconomicsShowCommand::class,
                QualityCommand::class,
                ValidateSpecCommand::class,
                ValidatePlanCommand::class,
                SpecApproveCommand::class,
                SpecDeleteCommand::class,
                TrackerStatusCommand::class,
                TrackerPushCommand::class,
                TrackerPullCommand::class,
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
