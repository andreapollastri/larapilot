<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\BackstageService;
use Larapilot\Services\ConfigService;
use Larapilot\Support\AtomicFile;
use Larapilot\Support\LarapilotCommand;

class BackstageExportCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:backstage-export
                            {--write : Write the catalog descriptor, MkDocs config, and TechDocs sources into the project}
                            {--force : Overwrite an existing catalog-info.yaml / mkdocs.yml}
                            {--catalog= : Path for the Backstage catalog descriptor (default: catalog-info.yaml)}
                            {--mkdocs= : Path for the MkDocs/TechDocs config (default: mkdocs.yml)}
                            {--no-techdocs : Skip TechDocs generation — catalog descriptor only}
                            {--file= : Write the JSON bundle to this path instead of returning it in the envelope}
                            {--api-base= : Absolute Larapilot API base URL for entity links (e.g. https://app.test/larapilot/api)}';

    protected $description = 'Export Backstage catalog entities, TechDocs sources, and a delivery snapshot from .larapilot/';

    public function handle(BackstageService $backstage, ConfigService $config): int
    {
        if (! $backstage->enabled()) {
            return $this->failure(
                'E_PRECONDITION',
                'The Backstage integration is disabled.',
                $this->exitForCode('E_PRECONDITION'),
                'Set larapilot.backstage.enabled (LARAPILOT_BACKSTAGE_ENABLED) to true.'
            );
        }

        $apiBase = $this->stringOption('api-base');

        if ((bool) $this->option('write')) {
            if (! $config->hasProjectConfig()) {
                return $this->failure(
                    'E_PRECONDITION',
                    'Larapilot is not installed in this project.',
                    $this->exitForCode('E_PRECONDITION'),
                    'Run php artisan larapilot:install first.'
                );
            }

            $report = $backstage->write([
                'force' => (bool) $this->option('force'),
                'techdocs' => ! (bool) $this->option('no-techdocs'),
                'api_base' => $apiBase,
                'catalog' => $this->stringOption('catalog'),
                'mkdocs' => $this->stringOption('mkdocs'),
            ]);

            return $this->success('backstage-export', $report + [
                'hint' => $this->skippedHint($report),
            ]);
        }

        $bundle = $backstage->bundle($apiBase);
        $file = $this->stringOption('file');

        if ($file !== null) {
            $json = json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($json === false) {
                return $this->failure('E_INTERNAL', 'Failed to encode the Backstage bundle as JSON.', $this->exitForCode('E_INTERNAL'));
            }

            AtomicFile::write($file, $json.PHP_EOL);

            return $this->success('backstage-export', [
                'path' => $file,
                'generated_at' => $bundle['generated_at'],
                'entity_refs' => $bundle['catalog']['entity_refs'],
            ]);
        }

        return $this->success('backstage-export', $bundle);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    protected function skippedHint(array $report): ?string
    {
        $skipped = [];

        foreach (['catalog', 'mkdocs'] as $key) {
            $entry = $report[$key] ?? null;

            if (is_array($entry) && ($entry['skipped'] ?? false) === true) {
                $skipped[] = (string) $entry['path'];
            }
        }

        return $skipped === []
            ? null
            : 'Kept existing '.implode(' and ', $skipped).'. Re-run with --force to overwrite.';
    }

    protected function stringOption(string $key): ?string
    {
        $value = $this->option($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
