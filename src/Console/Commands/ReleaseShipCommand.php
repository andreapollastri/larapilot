<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\ReleaseFlowService;

class ReleaseShipCommand extends ReleaseBranchCommand
{
    protected $signature = 'larapilot:release-ship
                            {--semver= : SemVer X.Y.Z}
                            {--push : Push main, develop, and the version tag}';

    protected $description = 'Merge the release branch into main, tag it, and back-merge into develop';

    public function handle(ConfigService $config, ReleaseFlowService $flow): int
    {
        if (($blocked = $this->guardReleaseMode($config)) !== null) {
            return $blocked;
        }

        $version = (string) ($this->option('semver') ?? '');

        if (trim($version) === '') {
            return $this->failure(
                'E_INVALID_INPUT',
                '--semver is required.',
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        try {
            $result = $flow->ship($version, (bool) $this->option('push'));
        } catch (\InvalidArgumentException $exception) {
            return $this->invalidRelease($exception);
        }

        return $this->flowResult('release_ship', $result);
    }
}
