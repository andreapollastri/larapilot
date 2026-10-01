<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\UpgradeService;
use Larapilot\Support\LarapilotCommand;

class UpgradeCheckCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:upgrade-check
                            {--laravel= : Target Laravel major, e.g. 13}
                            {--php= : Target PHP version, e.g. 8.4}
                            {--php-from= : The PHP the project is on, when composer.json does not say it}
                            {--db= : Target database, e.g. pgsql:17, mysql:8.4, mariadb:11.4}
                            {--db-from= : The database of today when the server does not answer, e.g. mysql:5.7}
                            {--offline : Read the lock only; do not ask Packagist which releases support the target}
                            {--report : Write the readiness report to {paths.upgrades}}
                            {--gate : Exit 1 when the verdict is blocked}';

    protected $description = 'Readiness of the project for a Laravel, PHP, or database upgrade: dependencies, pinned versions, code to change, criticalities';

    public function handle(UpgradeService $upgrades, ConfigService $config): int
    {
        try {
            $check = $upgrades->check([
                'laravel' => $this->stringOption('laravel'),
                'php' => $this->stringOption('php'),
                'php_from' => $this->stringOption('php-from'),
                'db' => $this->stringOption('db'),
                'db_from' => $this->stringOption('db-from'),
            ], (bool) $this->option('offline'));
        } catch (\InvalidArgumentException $e) {
            return $this->failure('E_INVALID_INPUT', $e->getMessage(), $this->exitForCode('E_INVALID_INPUT'));
        }

        $check['report'] = $this->option('report')
            ? $config->relativePath($upgrades->writeReport($check))
            : null;

        $this->success('upgrade_check', $check);

        return $this->option('gate') && $check['verdict'] === 'blocked' ? self::FAILURE : self::SUCCESS;
    }

    protected function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
