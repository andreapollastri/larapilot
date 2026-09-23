<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\ReleaseFlowService;

class ReleaseFeatureCommand extends ReleaseBranchCommand
{
    protected $signature = 'larapilot:release-feature
                            {--semver= : Release to branch from; inferred when only one release is in progress}
                            {--spec= : US-XXX}
                            {--slug= : Short branch suffix}
                            {--no-checkout : Create the branch without switching to it}
                            {--push : Push the release branch and the feature branch}';

    protected $description = 'Create the feature branch for a spec from its release branch';

    public function handle(ConfigService $config, ReleaseFlowService $flow): int
    {
        if (($blocked = $this->guardReleaseMode($config)) !== null) {
            return $blocked;
        }

        $spec = (string) ($this->option('spec') ?? '');

        if (trim($spec) === '') {
            return $this->failure(
                'E_INVALID_INPUT',
                '--spec is required.',
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        $version = $this->option('semver');
        $slug = $this->option('slug');

        try {
            $result = $flow->feature(
                is_string($version) && trim($version) !== '' ? $version : null,
                $spec,
                is_string($slug) && trim($slug) !== '' ? $slug : null,
                ! (bool) $this->option('no-checkout'),
                (bool) $this->option('push')
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->invalidRelease($exception);
        }

        return $this->flowResult('release_feature', $result);
    }
}
