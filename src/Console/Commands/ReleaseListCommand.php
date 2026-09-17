<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\ReleaseService;
use Larapilot\Support\LarapilotCommand;

class ReleaseListCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:release-list {--status= : Filter by planned|in_progress|shipped}';

    protected $description = 'List releases from the ledger (.larapilot/releases.yaml)';

    public function handle(ConfigService $config, ReleaseService $releases): int
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

        return $this->success('release_list', [
            'releases' => $items,
            'count' => count($items),
            'path' => $releases->path(),
            'open_count' => count($releases->openReleases()),
        ]);
    }
}
