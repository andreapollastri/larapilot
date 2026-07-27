<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\Tracker\TrackerException;
use Larapilot\Services\TrackerService;
use Larapilot\Support\LarapilotCommand;

class TrackerPullCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:tracker-pull
                            {--spec=* : Only check these spec codes (repeatable); default is every linked spec}
                            {--apply : Write the mapped remote status back into the backlog (DONE is never applied)}';

    protected $description = 'Read the project tracker and report drift against the backlog; --apply writes statuses back';

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
            $report = $tracker->pull([
                'codes' => $this->specCodes(),
                'apply' => (bool) $this->option('apply'),
            ]);
        } catch (TrackerException $exception) {
            return $this->failure(
                'E_CONNECTOR',
                $exception->getMessage(),
                $this->exitForCode('E_CONNECTOR'),
                'Run php artisan larapilot:tracker-status --ping to check credentials and the target board.'
            );
        }

        if ($report['errors'] !== []) {
            return $this->failure(
                'E_CONNECTOR',
                count($report['errors']).' linked stories could not be read.',
                $this->exitForCode('E_CONNECTOR'),
                'See details for the per-spec provider errors.',
                $report
            );
        }

        return $this->success('tracker-pull', $report + [
            'hint' => $this->hint($report),
        ]);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    protected function hint(array $report): ?string
    {
        $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
        $drifted = (int) ($summary['drifted'] ?? 0);

        if ($drifted === 0) {
            return null;
        }

        if ((bool) ($report['apply'] ?? false)) {
            return 'Statuses that map to DONE were left alone — approve them through /larapilot-review.';
        }

        return $drifted.' stories drifted. Re-run with --apply to write the mapped statuses into the backlog.';
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
