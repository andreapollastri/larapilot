<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\SbomService;
use Larapilot\Services\VendorAuditService;
use Larapilot\Support\AtomicFile;
use Larapilot\Support\LarapilotCommand;

class SbomCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:sbom
                            {--full : List every component, not only the summary}
                            {--write= : Write the SBOM under {paths.security}: md, cyclonedx, or both}';

    protected $description = 'The software bill of materials — Composer and JavaScript dependencies, the frontend companion included — from the lockfiles';

    public function handle(SbomService $sbom, VendorAuditService $audit, ConfigService $config): int
    {
        $write = $this->option('write');

        if ($write !== null && ! in_array($write, ['md', 'cyclonedx', 'both'], true)) {
            return $this->failure('E_INVALID_INPUT', '--write takes md, cyclonedx, or both.', $this->exitForCode('E_INVALID_INPUT'));
        }

        $inventory = $sbom->inventory();
        $latest = $audit->latest();
        $written = [];

        if ($write !== null) {
            $directory = rtrim((string) $config->setupInfo()['paths']['security'], '/');

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            if ($write === 'md' || $write === 'both') {
                AtomicFile::write($directory.'/sbom.md', $sbom->markdown($latest));
                $written[] = $config->relativePath($directory.'/sbom.md');
            }

            if ($write === 'cyclonedx' || $write === 'both') {
                AtomicFile::write($directory.'/sbom.cdx.json', (string) json_encode($sbom->cyclonedx($latest), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $written[] = $config->relativePath($directory.'/sbom.cdx.json');
            }
        }

        $data = [
            'project' => $inventory['project'],
            'inventories' => $inventory['inventories'],
            'totals' => $inventory['totals'],
            'licenses' => [
                'classes' => $inventory['licenses']['classes'],
                'top' => array_slice($inventory['licenses']['licenses'], 0, 10),
                'copyleft' => $inventory['licenses']['copyleft'],
            ],
            'abandoned' => array_values(array_map(
                static fn (array $component): array => ['name' => $component['name'], 'version' => $component['version'], 'replacement' => is_string($component['abandoned']) ? $component['abandoned'] : null],
                array_filter($inventory['components'], static fn (array $component): bool => $component['abandoned'] !== false)
            )),
            'vulnerabilities' => $latest === null ? null : [
                'checked_at' => $latest['checked_at'],
                'stale' => $latest['stale'],
                'counts' => $latest['counts'],
            ],
            'written' => $written,
        ];

        if ($this->option('full')) {
            $data['components'] = $inventory['components'];
        }

        return $this->success('sbom', $data);
    }
}
