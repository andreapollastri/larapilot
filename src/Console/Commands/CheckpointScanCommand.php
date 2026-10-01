<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\CheckpointService;
use Larapilot\Support\LarapilotCommand;

class CheckpointScanCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:checkpoint-scan
                            {--only= : Comma-separated check names to run}
                            {--skip= : Comma-separated check names to skip}
                            {--cached : Answer the last scan instead of running it again}
                            {--report : Write the result to {paths.security}/checkpoint.md}
                            {--fail-on-warn : With --gate, a warning fails too}
                            {--gate : Exit 1 when a check fails}';

    protected $description = 'Run Checkpoint (andreapollastri/checkpoint) and keep the result for the Security page of the dashboard';

    public function handle(CheckpointService $checkpoint): int
    {
        if ($this->option('cached')) {
            $summary = $checkpoint->latest();

            if ($summary === null) {
                return $this->failure('E_NOT_FOUND', 'No Checkpoint scan was kept yet.', $this->exitForCode('E_NOT_FOUND'), 'Run: php artisan larapilot:checkpoint-scan');
            }
        } else {
            if (! $checkpoint->installed()) {
                return $this->failure(
                    'E_PRECONDITION',
                    'Checkpoint is not installed in this project.',
                    $this->exitForCode('E_PRECONDITION'),
                    'composer require --dev andreapollastri/checkpoint'
                );
            }

            try {
                $summary = $checkpoint->scan($this->names('only'), $this->names('skip'));
            } catch (\RuntimeException $e) {
                return $this->failure('E_CONNECTOR', $e->getMessage(), $this->exitForCode('E_CONNECTOR'));
            }
        }

        $summary['report'] = $this->option('report') ? $checkpoint->writeReport($summary)['relative'] : null;
        unset($summary['history']);

        $this->success('checkpoint_scan', $summary);

        $fails = $summary['verdict'] === 'FAIL' || ($this->option('fail-on-warn') && $summary['verdict'] === 'WARN');

        return $this->option('gate') && $fails ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    protected function names(string $option): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->option($option)))));
    }
}
