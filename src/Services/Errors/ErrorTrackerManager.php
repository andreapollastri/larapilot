<?php

declare(strict_types=1);

namespace Larapilot\Services\Errors;

use Larapilot\Services\ConfigService;
use Larapilot\Services\Errors\Drivers\BoogleDriver;
use Larapilot\Services\Errors\Drivers\BugsnagDriver;
use Larapilot\Services\Errors\Drivers\CloudWatchDriver;
use Larapilot\Services\Errors\Drivers\DatadogDriver;
use Larapilot\Services\Errors\Drivers\FlareDriver;
use Larapilot\Services\Errors\Drivers\HoneybadgerDriver;
use Larapilot\Services\Errors\Drivers\RollbarDriver;
use Larapilot\Services\Errors\Drivers\SentryDriver;

/**
 * The one tracker the project reads its production errors from, chosen
 * with `settings.errors_provider`.
 */
class ErrorTrackerManager
{
    /**
     * @var array<string, class-string<ErrorTrackerDriver>>
     */
    protected const DRIVERS = [
        'boogle' => BoogleDriver::class,
        'sentry' => SentryDriver::class,
        'bugsnag' => BugsnagDriver::class,
        'flare' => FlareDriver::class,
        'datadog' => DatadogDriver::class,
        'rollbar' => RollbarDriver::class,
        'honeybadger' => HoneybadgerDriver::class,
        'cloudwatch' => CloudWatchDriver::class,
    ];

    protected ?ErrorTrackerDriver $driver = null;

    public function __construct(protected ConfigService $config) {}

    /**
     * @return list<string>
     */
    public function available(): array
    {
        return array_keys(self::DRIVERS);
    }

    /**
     * The provider the project chose, as it was written — known or not,
     * with the setting on or off.
     */
    public function configured(): string
    {
        return $this->config->errorsProvider();
    }

    /**
     * The provider the project chose, when it is one Larapilot knows.
     */
    public function provider(): ?string
    {
        $provider = $this->configured();

        return isset(self::DRIVERS[$provider]) ? $provider : null;
    }

    public function driver(): ErrorTrackerDriver
    {
        $provider = $this->provider();

        if ($provider === null) {
            $configured = $this->configured();

            throw new ErrorTrackerException(
                $configured === '' ? 'No errors provider is set for this project.' : 'Unknown errors provider "'.$configured.'".',
                'Pick one with: php artisan larapilot:settings-set --errors=YES --errors-provider='.implode('|', $this->available())
            );
        }

        // The setting can change while the process lives: a driver is
        // kept only as long as it is the one chosen.
        if ($this->driver === null || $this->driver->provider() !== $provider) {
            $this->driver = app(self::DRIVERS[$provider]);
        }

        return $this->driver;
    }

    public function tryDriver(): ?ErrorTrackerDriver
    {
        return $this->provider() === null ? null : $this->driver();
    }

    public function label(): string
    {
        return $this->tryDriver()?->label() ?? 'The error tracker';
    }
}
