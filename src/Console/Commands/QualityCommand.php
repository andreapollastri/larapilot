<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\CodeQualityService;
use Larapilot\Support\LarapilotCommand;

class QualityCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:quality
                            {--fix : Apply Pint fixes instead of check-only mode}';

    protected $description = 'Run Laravel Pint and Larastan (PHPStan level '.CodeQualityService::MIN_LARASTAN_LEVEL.'+)';

    public function handle(CodeQualityService $quality): int
    {
        $status = $quality->status();

        if (! $status['larastan_level_ok']) {
            return $this->failure(
                'E_INVALID_INPUT',
                'Larastan level must be at least '.CodeQualityService::MIN_LARASTAN_LEVEL.'.',
                $this->exitForCode('E_INVALID_INPUT'),
                'Set level: '.CodeQualityService::MIN_LARASTAN_LEVEL.' (or higher) in phpstan.neon(.dist). Never lower without an explicit human waiver.'
            );
        }

        if (! $status['pint_binary'] || ! $status['phpstan_binary']) {
            return $this->failure(
                'E_PRECONDITION',
                'Pint or PHPStan is not installed.',
                $this->exitForCode('E_PRECONDITION'),
                'Run larapilot:install (or composer require --dev laravel/pint larastan/larastan) then composer update.'
            );
        }

        $result = $quality->run((bool) $this->option('fix'));

        if (($result['pint']['output'] ?? '') !== '') {
            $this->line('Pint:');
            $this->line($result['pint']['output']);
        }

        if (($result['analyse']['output'] ?? '') !== '') {
            $this->newLine();
            $this->line('Larastan:');
            $this->line($result['analyse']['output']);
        }

        if (($result['ok'] ?? false) !== true) {
            return $this->failure(
                'E_QUALITY',
                'Code quality checks failed.',
                self::FAILURE,
                'Fix Pint/Larastan findings or run larapilot:quality --fix for formatting.'
            );
        }

        return $this->success('quality', $result);
    }
}
