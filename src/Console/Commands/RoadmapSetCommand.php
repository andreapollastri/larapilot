<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\RoadmapService;
use Larapilot\Support\LarapilotCommand;

/**
 * Curate the public roadmap: the intro, the public wording of a version,
 * the public wording of one of its stories, and what stays out of sight.
 */
class RoadmapSetCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:roadmap-set
                            {--intro= : The paragraph that opens the public roadmap (empty string clears it)}
                            {--semver= : The release to curate, X.Y.Z}
                            {--item= : A story of that release, US-XXX; the wording then applies to it}
                            {--title= : Public title of the version or the story (empty string restores the ledger title)}
                            {--summary= : One public line under the title (empty string clears it)}
                            {--target= : When the version is due, as told outside: a quarter, a month, or YYYY-MM-DD (version only)}
                            {--hide : Keep the version or the story off the public roadmap}
                            {--show : Put it back}';

    protected $description = 'Curate the public roadmap wording in .larapilot/roadmap.yaml';

    public function handle(RoadmapService $roadmap): int
    {
        $changes = [];

        foreach (['intro', 'title', 'summary', 'target'] as $option) {
            $value = $this->option($option);

            if ($value !== null && $value !== false) {
                $changes[$option] = (string) $value;
            }
        }

        $semver = $this->option('semver');
        $item = $this->option('item');

        if (is_string($semver) && trim($semver) !== '') {
            $changes['version'] = trim($semver);
        }

        if (is_string($item) && trim($item) !== '') {
            if (! isset($changes['version'])) {
                return $this->failure('E_INVALID_INPUT', '--item needs --semver: the story is curated inside its release.', $this->exitForCode('E_INVALID_INPUT'));
            }

            $changes['item'] = trim($item);
        }

        if ((bool) $this->option('hide') && (bool) $this->option('show')) {
            return $this->failure('E_INVALID_INPUT', 'Pass --hide or --show, not both.', $this->exitForCode('E_INVALID_INPUT'));
        }

        if ((bool) $this->option('hide')) {
            $changes['hidden'] = true;
        } elseif ((bool) $this->option('show')) {
            $changes['hidden'] = false;
        }

        if (isset($changes['target']) && isset($changes['item'])) {
            return $this->failure('E_INVALID_INPUT', '--target belongs to a version, not to a story.', $this->exitForCode('E_INVALID_INPUT'));
        }

        if (($changes['hidden'] ?? null) !== null && ! isset($changes['version'])) {
            return $this->failure('E_INVALID_INPUT', '--hide and --show need --semver.', $this->exitForCode('E_INVALID_INPUT'));
        }

        $wording = array_intersect_key($changes, array_flip(['title', 'summary', 'target', 'hidden']));

        if ($wording !== [] && ! isset($changes['version'])) {
            return $this->failure('E_INVALID_INPUT', '--title, --summary, and --target need --semver (and --item for a story).', $this->exitForCode('E_INVALID_INPUT'));
        }

        if ($changes === []) {
            return $this->failure(
                'E_INVALID_INPUT',
                'Nothing to set.',
                $this->exitForCode('E_INVALID_INPUT'),
                'Pass --intro, or --semver with --title / --summary / --target / --hide / --show, adding --item=US-XXX for one story.'
            );
        }

        try {
            $curated = $roadmap->curate($changes);
        } catch (\InvalidArgumentException $exception) {
            return $this->failure('E_INVALID_INPUT', $exception->getMessage(), $this->exitForCode('E_INVALID_INPUT'));
        }

        return $this->success('roadmap_set', [
            'path' => $roadmap->path(),
            'intro' => $curated['intro'],
            'version' => isset($changes['version']) ? ($curated['versions'][ltrim($changes['version'], 'vV')] ?? null) : null,
            'public_url' => $roadmap->publicUrl(),
        ]);
    }
}
