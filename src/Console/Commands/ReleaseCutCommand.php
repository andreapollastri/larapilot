<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\ReleaseFlowService;

class ReleaseCutCommand extends ReleaseBranchCommand
{
    protected $signature = 'larapilot:release-cut
                            {--semver= : SemVer X.Y.Z}
                            {--no-checkout : Create the branch without switching to it}
                            {--push : Push the release branch to origin}';

    protected $description = 'Open the Gitflow release branch and mark the release in progress';

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
            $result = $flow->cut($version, ! (bool) $this->option('no-checkout'), (bool) $this->option('push'));
        } catch (\InvalidArgumentException $exception) {
            return $this->invalidRelease($exception);
        }

        return $this->flowResult('release_cut', $result);
    }
}
