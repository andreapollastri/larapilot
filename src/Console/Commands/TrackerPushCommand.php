<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\Tracker\TrackerException;
use Larapilot\Services\TrackerService;
use Larapilot\Support\LarapilotCommand;

class TrackerPushCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:tracker-push
                            {--spec=* : Only push these spec codes (repeatable); default is the whole backlog}
                            {--dry-run : Report what would change without calling the provider}
                            {--force : Push every selected spec even when nothing changed since the last push}';

    protected $description = 'Push the backlog (and plan tasks as native subtasks) into the configured project tracker';

    public function handle(TrackerService $tracker, ConfigService $config): int
    {
        if (! $config->hasProjectConfig()) {
            return $this->failure(
                'E_PRECONDITION',
                'Larapilot is not installed in this project.',
                $this->exitForCode('E_PRECONDITION'),
                'Run php artisan larapilot:install first.'
            );
        }

        try {
            $report = $tracker->push([
                'codes' => $this->specCodes(),
                'dry_run' => (bool) $this->option('dry-run'),
                'force' => (bool) $this->option('force'),
            ]);
        } catch (TrackerException $exception) {
            return $this->failure(
                'E_CONNECTOR',
                $exception->getMessage(),
                $this->exitForCode('E_CONNECTOR'),
                'Run php artisan larapilot:tracker-status --ping to check credentials and the target board.'
            );
        }

        // Per-spec failures do not abort the run, but they must not read as
        // success either — the envelope carries both.
        if ($report['errors'] !== []) {
            // `stories` holds the successes only, so the attempted total is
            // both lists together.
            return $this->failure(
                'E_CONNECTOR',
                count($report['errors']).' of '.(count($report['stories']) + count($report['errors']))
                    .' stories failed to sync.',
                $this->exitForCode('E_CONNECTOR'),
                'See details for the per-spec provider errors.',
                $report
            );
        }

        return $this->success('tracker-push', $report);
    }

    /**
     * @return list<string>
     */
    protected function specCodes(): array
    {
        $codes = $this->option('spec');

        if (! is_array($codes)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $code): string => trim((string) $code),
            $codes
        ), fn (string $code): bool => $code !== ''));
    }
}
