<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\SpecService;
use Larapilot\Services\VersionService;
use Larapilot\Support\LarapilotCommand;

class SpecListCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:spec-list
                            {--status= : Filter by workflow status}
                            {--release= : Filter by version: x.y.z, several comma-separated, or none}
                            {--full : Every field of every spec, bodies and status history included}';

    protected $description = 'List backlog specs and summary metadata';

    public function handle(SpecService $specs, VersionService $versions): int
    {
        $status = $this->option('status');
        $data = $this->option('full') ? $specs->list($status) : $specs->overview($status);

        if (! $versions->enabled()) {
            return $this->success('spec_list', $data);
        }

        // Each spec says the version it targets.
        $assigned = $versions->assignments();
        $asked = trim((string) ($this->option('release') ?? ''));
        $selected = $asked !== '' ? $versions->selection($asked) : [];

        if ($asked !== '' && $selected === []) {
            return $this->failure(
                'E_NOT_FOUND',
                "No version {$asked} in the release ledger.",
                $this->exitForCode('E_NOT_FOUND'),
                'List the versions with: php artisan larapilot:release-list'
            );
        }

        $items = [];

        foreach ($data['items'] as $item) {
            $item['release'] = $assigned[(string) ($item['code'] ?? '')] ?? null;

            if ($selected === [] || in_array($item['release'] ?? VersionService::NONE, $selected, true)) {
                $items[] = $item;
            }
        }

        $data['items'] = $items;

        // The summary stays the backlog's: `last_code` numbers the next spec.
        if ($selected !== []) {
            $data['release'] = ['versions' => $selected, 'count' => count($items)];
        }

        return $this->success('spec_list', $data);
    }
}
