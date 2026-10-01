<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\ReleaseFlowService;

class ReleaseShipCommand extends ReleaseBranchCommand
{
    protected $signature = 'larapilot:release-ship
                            {--semver= : SemVer X.Y.Z}
                            {--push : Push main, develop, and the version tag}
                            {--skill-hooks-done= : Skill hooks of the before phase already run, comma-separated (settings.hooks)}';

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

        $hookContext = ['release' => trim($version), 'push' => (bool) $this->option('push')];

        if (($blocked = $this->beforeHooks('release.shipped', $hookContext)) !== null) {
            return $blocked;
        }

        try {
            $result = $flow->ship($version, (bool) $this->option('push'));
        } catch (\InvalidArgumentException $exception) {
            return $this->invalidRelease($exception);
        }

        if (($result['ok'] ?? false) === true) {
            $this->afterHooks('release.shipped', $hookContext);
        }

        return $this->flowResult('release_ship', $result);
    }
}
