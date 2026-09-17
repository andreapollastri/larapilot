<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\ReleaseService;
use Larapilot\Support\LarapilotCommand;

class ReleaseSetCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:release-set
                            {--semver= : SemVer X.Y.Z}
                            {--status= : planned|in_progress|shipped}
                            {--title= : Short release name}
                            {--branch= : Git branch}
                            {--specs= : Comma-separated US-XXX codes (replaces list)}
                            {--add-spec= : Append one US-XXX}
                            {--shipped-at= : ISO timestamp when status becomes shipped}';

    protected $description = 'Update an existing release in the ledger';

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

        $version = (string) ($this->option('semver') ?? '');

        if (trim($version) === '') {
            return $this->failure(
                'E_INVALID_INPUT',
                '--semver is required.',
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        $partial = [];

        foreach (['status', 'title', 'branch', 'shipped-at'] as $option) {
            $value = $this->option($option);

            if ($value === null || $value === false || $value === '') {
                continue;
            }

            $key = str_replace('-', '_', $option);
            $partial[$key] = $value;
        }

        $specs = $this->option('specs');

        if (is_string($specs) && trim($specs) !== '') {
            $partial['specs'] = array_values(array_filter(array_map(
                static fn (string $part): string => strtoupper(trim($part)),
                explode(',', $specs)
            ), static fn (string $part): bool => $part !== ''));
        }

        $addSpec = $this->option('add-spec');

        if (is_string($addSpec) && trim($addSpec) !== '') {
            $partial['add_spec'] = strtoupper(trim($addSpec));
        }

        if ($partial === []) {
            return $this->failure(
                'E_INVALID_INPUT',
                'Provide at least one of --status, --title, --branch, --specs, --add-spec, or --shipped-at.',
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        try {
            $entry = $releases->set($version, $partial);
        } catch (\InvalidArgumentException $e) {
            return $this->failure(
                'E_INVALID_INPUT',
                $e->getMessage(),
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        return $this->success('release', [
            'release' => $entry,
            'path' => $releases->path(),
        ]);
    }
}
