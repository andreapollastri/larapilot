<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\SpecService;
use Larapilot\Services\VersionService;
use Larapilot\Support\LarapilotCommand;

class SpecNextCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:spec-next
                            {--status= : Status filter (defaults to TODO)}
                            {--release= : Only the stories of this version (x.y.z), or of none (none)}
                            {--task= : Return only this task id, e.g. TASK-01}
                            {--fields= : Comma-separated task keys to keep (id is always kept)}';

    protected $description = 'Auto-select the first eligible spec: the version the work is on first, then by priority and code';

    public function handle(SpecService $specs, ConfigService $config, VersionService $versions): int
    {
        $status = $this->option('status') ?? $config->status('todo');
        $asked = trim((string) ($this->option('release') ?? ''));
        $ranks = [];
        $only = null;
        $current = null;

        if ($asked !== '') {
            $selected = $versions->enabled() ? $versions->selection($asked) : [];

            if ($selected === []) {
                return $this->failure(
                    'E_NOT_FOUND',
                    "No version {$asked} in the release ledger.",
                    $this->exitForCode('E_NOT_FOUND'),
                    'List the versions with: php artisan larapilot:release-list'
                );
            }

            $assigned = $versions->assignments();
            $only = array_values(array_filter(
                array_map(static fn (array $spec): string => (string) ($spec['code'] ?? ''), $specs->allSpecs()),
                static fn (string $code): bool => in_array($assigned[$code] ?? VersionService::NONE, $selected, true)
            ));
        } elseif ($config->releaseModeEnabled()) {
            // The version the work is on goes first, then the open ones in
            // order: a version is finished before the next is started.
            $current = $versions->current();
            $ranks = $versions->ranks(null, $current['version'] ?? null);
        }

        $data = $specs->next($status, $ranks, $only);

        if ($data === null) {
            return $this->failure(
                'E_PRECONDITION',
                $asked !== '' ? "No eligible specs found for status {$status} in version {$asked}." : "No eligible specs found for status {$status}.",
                $this->exitForCode('E_PRECONDITION'),
                'Create specs with larapilot-spec or add items via larapilot:spec-add.'
            );
        }

        $task = $this->option('task');
        $sliced = $specs->slice($data, is_string($task) ? $task : null, $this->option('fields') !== null ? (string) $this->option('fields') : null);

        if ($sliced === null) {
            return $this->failure(
                'E_NOT_FOUND',
                "Task {$task} not found on ".($data['spec']['code'] ?? 'spec').'.',
                $this->exitForCode('E_NOT_FOUND')
            );
        }

        if ($versions->enabled()) {
            $sliced['release'] = [
                'version' => $versions->assignments()[(string) ($data['spec']['code'] ?? '')] ?? null,
                'current' => $current['version'] ?? null,
            ];
        }

        return $this->success('spec_detail', $sliced);
    }
}
