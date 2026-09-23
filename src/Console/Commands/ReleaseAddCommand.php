<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\ReleaseFlowService;
use Larapilot\Services\ReleaseService;
use Larapilot\Support\LarapilotCommand;

class ReleaseAddCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:release-add
                            {--semver= : SemVer X.Y.Z}
                            {--title= : Short release name}
                            {--status=planned : planned|in_progress|shipped}
                            {--branch= : Git branch (default release/x.y.z)}
                            {--specs= : Comma-separated US-XXX codes}';

    protected $description = 'Register a new release in the ledger';

    public function handle(ConfigService $config, ReleaseService $releases, ReleaseFlowService $flow): int
    {
        if (! $config->releaseModeEnabled()) {
            return $this->failure(
                'E_PRECONDITION',
                'Release mode is disabled (settings.release_mode = NO).',
                $this->exitForCode('E_PRECONDITION'),
                'Enable with: php artisan larapilot:settings-set --release-mode=YES'
            );
        }

        $version = (string) ($this->option('semver') ?? '');
        $title = (string) ($this->option('title') ?? '');

        if (trim($version) === '' || trim($title) === '') {
            return $this->failure(
                'E_INVALID_INPUT',
                'Both --semver and --title are required.',
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        $specs = $this->parseSpecs((string) ($this->option('specs') ?? ''));
        $branch = $this->option('branch');
        $branch = is_string($branch) && trim($branch) !== '' ? trim($branch) : null;

        try {
            $entry = $releases->add(
                $version,
                $title,
                (string) ($this->option('status') ?? 'planned'),
                $specs,
                $branch
            );
        } catch (\InvalidArgumentException $e) {
            return $this->failure(
                'E_INVALID_INPUT',
                $e->getMessage(),
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        $git = $this->openBranch($flow, $entry);

        return $this->success('release', [
            'release' => is_array($git) && is_array($git['release'] ?? null) ? $git['release'] : $entry,
            'path' => $releases->path(),
            'git' => $git,
        ]);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>|null
     */
    protected function openBranch(ReleaseFlowService $flow, array $entry): ?array
    {
        if (($entry['status'] ?? '') !== 'in_progress') {
            return null;
        }

        try {
            return $flow->cut((string) $entry['version'], false);
        } catch (\InvalidArgumentException $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        }
    }

    /**
     * @return list<string>
     */
    protected function parseSpecs(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $part): string => strtoupper(trim($part)),
            explode(',', $raw)
        ), static fn (string $part): bool => $part !== ''));
    }
}
