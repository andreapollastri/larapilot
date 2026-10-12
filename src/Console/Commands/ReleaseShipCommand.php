<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\ReleaseFlowService;
use Larapilot\Services\ReleaseService;
use Larapilot\Services\VersionService;

class ReleaseShipCommand extends ReleaseBranchCommand
{
    protected $signature = 'larapilot:release-ship
                            {--semver= : SemVer X.Y.Z}
                            {--push : Push main, develop, and the version tag}
                            {--defer-to= : Move the stories still open to this version (x.y.z), or to the backlog (backlog), then ship}
                            {--force : Ship with stories still open; the release notes list them as not delivered}
                            {--skill-hooks-done= : Skill hooks of the before phase already run, comma-separated (settings.hooks)}';

    protected $description = 'Merge the release branch into main, tag it, and back-merge into develop';

    public function handle(ConfigService $config, ReleaseFlowService $flow, VersionService $versions, ReleaseService $releases): int
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

        // A version ships what it promised: its open stories are delivered,
        // moved to another version, or knowingly left behind.
        try {
            $open = $versions->openStories($version);
        } catch (\InvalidArgumentException $exception) {
            return $this->invalidRelease($exception);
        }

        $deferTo = trim((string) ($this->option('defer-to') ?? ''));
        $deferred = null;

        if ($open !== [] && $deferTo === '' && ! $this->option('force')) {
            return $this->failure(
                'E_PRECONDITION',
                count($open).' '.(count($open) === 1 ? 'story' : 'stories').' of '.trim($version).' still open: '.implode(', ', array_column($open, 'code')).'.',
                $this->exitForCode('E_PRECONDITION'),
                'Deliver them, move them with --defer-to=x.y.z (or --defer-to=backlog), or ship anyway with --force.',
                ['open' => $open]
            );
        }

        $target = strtolower($deferTo) === 'backlog' ? null : $deferTo;

        if ($open !== [] && $target !== null) {
            try {
                $found = $releases->find($target);
            } catch (\InvalidArgumentException $exception) {
                return $this->invalidRelease($exception);
            }

            if ($found === null || ($found['status'] ?? '') === 'shipped' || $releases->normalizeVersion($target) === $releases->normalizeVersion($version)) {
                return $this->failure(
                    'E_INVALID_INPUT',
                    "--defer-to={$deferTo} is not another open version.",
                    $this->exitForCode('E_INVALID_INPUT'),
                    'Name a planned or in-progress version, or --defer-to=backlog.'
                );
            }
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

        // The ledger moves once the version is out, like its status: a
        // ship refused by git leaves the stories where they were.
        if (($result['ok'] ?? false) === true && $open !== [] && $deferTo !== '') {
            $releases->defer($version, array_column($open, 'code'), $target);
            $deferred = ['specs' => array_column($open, 'code'), 'to' => $target !== null ? ltrim($target, 'vV') : null];
        }

        if (($result['ok'] ?? false) === true) {
            $this->afterHooks('release.shipped', $hookContext);
        }

        $result['deferred'] = $deferred;
        $result['left_open'] = $deferTo === '' ? $open : [];

        return $this->flowResult('release_ship', $result);
    }
}
