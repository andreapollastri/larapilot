<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\ReleaseFlowService;
use Larapilot\Services\ReleaseService;
use Larapilot\Services\VersionService;
use Larapilot\Support\LarapilotCommand;

class ReleaseListCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:release-list {--status= : Filter by planned|in_progress|shipped}';

    protected $description = 'List releases from the ledger (.larapilot/releases.yaml)';

    public function handle(ConfigService $config, ReleaseService $releases, ReleaseFlowService $flow, VersionService $versions): int
    {
        if (! $config->releaseModeEnabled()) {
            return $this->failure(
                'E_PRECONDITION',
                'Release mode is disabled (settings.release_mode = NO).',
                $this->exitForCode('E_PRECONDITION'),
                'Enable with: php artisan larapilot:settings-set --release-mode=YES'
            );
        }

        $status = $this->option('status');

        if (is_string($status) && trim($status) !== '') {
            $normalized = strtolower(trim(str_replace('-', '_', (string) $status)));

            if (! in_array($normalized, ReleaseService::STATUSES, true)) {
                return $this->failure(
                    'E_INVALID_INPUT',
                    "Invalid --status value: {$status}.",
                    $this->exitForCode('E_INVALID_INPUT'),
                    'Allowed: '.implode(', ', ReleaseService::STATUSES).'.'
                );
            }
        }

        $items = $releases->list(is_string($status) && trim($status) !== '' ? (string) $status : null);
        $progress = [];

        foreach ($versions->rows() as $row) {
            $progress[$row['version']] = [
                'specs' => $row['counts'],
                'points' => $row['points'],
                'progress' => $row['progress'],
                'forecast_end' => $row['forecast_end'],
                'milestone' => $row['milestone'],
                'risk' => $row['risk'],
            ];
        }

        // Each release with what it has delivered, from the backlog: the
        // ledger names the stories, the backlog says where they stand.
        $items = array_map(
            static fn (array $release): array => $release + ['progress' => $progress[(string) ($release['version'] ?? '')] ?? null],
            $items
        );
        $current = $versions->current();

        return $this->success('release_list', [
            'releases' => $items,
            'count' => count($items),
            'path' => $releases->path(),
            'open_count' => count($releases->openReleases()),
            'current' => $current,
            'unassigned' => $versions->unassigned(),
            'git' => $flow->context(),
        ]);
    }
}
