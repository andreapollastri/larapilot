<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\ReleaseFlowService;

class ReleaseSyncCommand extends ReleaseBranchCommand
{
    protected $signature = 'larapilot:release-sync
                            {--semver= : Release to update from develop; inferred when only one release is in progress}';

    protected $description = 'Merge develop into the release branch';

    public function handle(ConfigService $config, ReleaseFlowService $flow): int
    {
        if (($blocked = $this->guardReleaseMode($config)) !== null) {
            return $blocked;
        }

        $version = $this->option('semver');

        try {
            $result = $flow->sync(is_string($version) && trim($version) !== '' ? $version : null);
        } catch (\InvalidArgumentException $exception) {
            return $this->invalidRelease($exception);
        }

        return $this->flowResult('release_sync', $result);
    }
}
