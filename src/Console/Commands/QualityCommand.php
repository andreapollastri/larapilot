<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\CodeQualityService;
use Larapilot\Support\LarapilotCommand;

class QualityCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:quality
                            {--fix : Apply Pint fixes instead of check-only mode (under quality_scope CHANGED, to the changed files only)}
                            {--baseline : Write phpstan-baseline.neon with the errors the project has today and include it from the PHPStan config}';

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

        if ((bool) $this->option('baseline')) {
            return $this->baseline($quality);
        }

        $presented = $quality->present($quality->run((bool) $this->option('fix')), $this->output->isVerbose());

        if (($presented['ok'] ?? false) !== true) {
            $hint = $presented['scope'] === 'CHANGED'
                ? 'Fix the Pint/Larastan findings, or run larapilot:quality --fix to format the changed files.'
                : 'Fix Pint/Larastan findings or run larapilot:quality --fix for formatting. On a codebase never formatted: settings-set --quality-scope=CHANGED and larapilot:quality --baseline.';

            if ($presented['scope'] === 'CHANGED' && ! $presented['baseline'] && ($presented['analyse']['ok'] ?? false) !== true) {
                $hint .= ' Larastan has no baseline: larapilot:quality --baseline keeps the errors the project already had out of the gate.';
            }

            return $this->failure(
                'E_QUALITY',
                'Code quality checks failed.',
                self::FAILURE,
                $hint,
                $presented
            );
        }

        return $this->success('quality', $presented);
    }

    protected function baseline(CodeQualityService $quality): int
    {
        $result = $quality->baseline();

        if ($result['ok'] !== true) {
            return $this->failure(
                'E_QUALITY',
                $result['config'] === null ? 'No PHPStan config to include the baseline from.' : 'The PHPStan baseline could not be written.',
                self::FAILURE,
                $result['config'] === null ? 'Run larapilot:install first: it writes phpstan.neon.dist.' : 'Run vendor/bin/phpstan analyse to see why it stops.',
                ['output' => $result['output']]
            );
        }

        unset($result['output']);

        return $this->success('quality-baseline', $result);
    }
}
