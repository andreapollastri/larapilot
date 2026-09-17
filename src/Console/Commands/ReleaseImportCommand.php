<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\GitService;
use Larapilot\Services\ReleaseService;
use Larapilot\Support\LarapilotCommand;

class ReleaseImportCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:release-import
                            {--dry-run : Preview imports without writing}
                            {--include-annotated : Include annotated tag messages as titles}';

    protected $description = 'Rebuild shipped releases from Git semver tags (vX.Y.Z or X.Y.Z)';

    public function handle(ConfigService $config, ReleaseService $releases, GitService $git): int
    {
        if (! $config->releaseModeEnabled()) {
            return $this->failure(
                'E_PRECONDITION',
                'Release mode is disabled (settings.release_mode = NO).',
                $this->exitForCode('E_PRECONDITION'),
                'Enable with: php artisan larapilot:settings-set --release-mode=YES'
            );
        }

        if (! $git->isRepository()) {
            return $this->failure(
                'E_PRECONDITION',
                'Not a Git repository — cannot import tags.',
                $this->exitForCode('E_PRECONDITION')
            );
        }

        $tags = $git->semverTags((bool) $this->option('include-annotated'));

        if ($tags === []) {
            return $this->success('release_import', [
                'imported' => [],
                'skipped' => [],
                'count' => 0,
                'dry_run' => (bool) $this->option('dry-run'),
                'hint' => 'No semver tags found. Expected vX.Y.Z or X.Y.Z.',
            ]);
        }

        try {
            $result = $releases->importFromTags($tags, (bool) $this->option('dry-run'));
        } catch (\InvalidArgumentException $e) {
            return $this->failure(
                'E_INVALID_INPUT',
                $e->getMessage(),
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        return $this->success('release_import', [
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
            'count' => count($result['imported']),
            'dry_run' => (bool) $this->option('dry-run'),
            'path' => $releases->path(),
        ]);
    }
}
