<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\VersionService;
use Larapilot\Support\LarapilotCommand;

class ReleaseShowCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:release-show
                            {--semver= : SemVer X.Y.Z; the version the work is on when omitted}';

    protected $description = 'One version: its stories, progress, forecast against its milestone, and release notes';

    public function handle(VersionService $versions): int
    {
        if (! $versions->enabled()) {
            return $this->failure(
                'E_PRECONDITION',
                'No version to show: release mode is off and the ledger is empty.',
                $this->exitForCode('E_PRECONDITION'),
                'Enable with: php artisan larapilot:settings-set --release-mode=YES'
            );
        }

        $version = trim((string) ($this->option('semver') ?? ''));

        if ($version === '') {
            $version = (string) ($versions->current()['version'] ?? '');

            if ($version === '') {
                return $this->failure(
                    'E_PRECONDITION',
                    'No open version: every version is shipped.',
                    $this->exitForCode('E_PRECONDITION'),
                    'Name one with --semver=, or register the next with larapilot:release-add.'
                );
            }
        }

        try {
            $found = $versions->show($version);
        } catch (\InvalidArgumentException $exception) {
            return $this->failure('E_INVALID_INPUT', $exception->getMessage(), $this->exitForCode('E_INVALID_INPUT'));
        }

        if ($found === null) {
            return $this->failure(
                'E_NOT_FOUND',
                "Release {$version} not found.",
                $this->exitForCode('E_NOT_FOUND'),
                'List the versions with: php artisan larapilot:release-list'
            );
        }

        unset($found['notes']);

        return $this->success('release_show', $found + [
            'open' => $versions->openStories($found['version']),
            'unassigned' => $versions->unassigned(),
        ]);
    }
}
