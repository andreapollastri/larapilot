<?php

declare(strict_types=1);

namespace Larapilot;

use Illuminate\Support\ServiceProvider;
use Larapilot\Console\Commands\ConfigShowCommand;
use Larapilot\Console\Commands\DoctorCommand;
use Larapilot\Console\Commands\InstallCommand;
use Larapilot\Console\Commands\MetricsCommand;
use Larapilot\Console\Commands\PrdWriteCommand;
use Larapilot\Console\Commands\SpecAddCommand;
use Larapilot\Console\Commands\SpecApproveCommand;
use Larapilot\Console\Commands\SpecDeleteCommand;
use Larapilot\Console\Commands\SpecListCommand;
use Larapilot\Console\Commands\SpecNextCommand;
use Larapilot\Console\Commands\SpecPlanCommand;
use Larapilot\Console\Commands\SpecRequestChangesCommand;
use Larapilot\Console\Commands\SpecReviewCommand;
use Larapilot\Console\Commands\SpecShowCommand;
use Larapilot\Console\Commands\SpecStartCommand;
use Larapilot\Console\Commands\TaskDoneCommand;
use Larapilot\Console\Commands\UpdateCommand;
use Larapilot\Console\Commands\ValidatePlanCommand;
use Larapilot\Console\Commands\ValidatePrdCommand;
use Larapilot\Console\Commands\ValidateSpecCommand;
use Larapilot\Http\MockupRouteRegistrar;
use Larapilot\Mcp\LarapilotServer;
use Larapilot\Services\ConfigService;
use Larapilot\Services\PlanService;
use Larapilot\Services\PrdService;
use Larapilot\Services\SpecService;
use Larapilot\Services\ValidationService;
use Laravel\Mcp\Facades\Mcp;

class LarapilotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/larapilot.php', 'larapilot');

        $this->app->singleton(ConfigService::class);
        $this->app->singleton(PrdService::class);
        $this->app->singleton(SpecService::class);
        $this->app->singleton(PlanService::class);
        $this->app->singleton(ValidationService::class);
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
                ConfigShowCommand::class,
                PrdWriteCommand::class,
                ValidatePrdCommand::class,
                SpecListCommand::class,
                SpecAddCommand::class,
                SpecShowCommand::class,
                SpecNextCommand::class,
                SpecPlanCommand::class,
                SpecStartCommand::class,
                SpecReviewCommand::class,
                SpecRequestChangesCommand::class,
                TaskDoneCommand::class,
                MetricsCommand::class,
                ValidateSpecCommand::class,
                ValidatePlanCommand::class,
                SpecApproveCommand::class,
                SpecDeleteCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/larapilot.php' => config_path('larapilot.php'),
            ], 'larapilot-config');
        }

        if (! config('larapilot.enabled', true)) {
            return;
        }

        Mcp::local('larapilot', LarapilotServer::class);

        MockupRouteRegistrar::register();
    }
}
