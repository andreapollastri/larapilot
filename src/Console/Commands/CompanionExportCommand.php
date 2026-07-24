<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\CompanionService;
use Larapilot\Support\AtomicFile;
use Larapilot\Support\LarapilotCommand;

class CompanionExportCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:companion-export
                            {--file= : Write the companion JSON bundle to this path instead of returning it in the envelope}
                            {--api-base= : Optional absolute Larapilot API base URL to embed in endpoints (e.g. https://app.test/larapilot/api)}';

    protected $description = 'Export the companion artifact bundle (PRD + frontend topology) for an external frontend repo';

    public function handle(CompanionService $companion): int
    {
        $apiBase = $this->option('api-base');
        $bundle = $companion->bundle(is_string($apiBase) && $apiBase !== '' ? $apiBase : null);

        $file = $this->option('file');

        if (is_string($file) && $file !== '') {
            $json = json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($json === false) {
                return $this->failure('E_INTERNAL', 'Failed to encode companion bundle as JSON.', $this->exitForCode('E_INTERNAL'));
            }

            AtomicFile::write($file, $json.PHP_EOL);

            return $this->success('companion-export', [
                'path' => $file,
                'generated_at' => $bundle['generated_at'],
                'has_prd' => ($bundle['artifacts']['prd'] ?? null) !== null,
                'frontend_topology' => $bundle['artifacts']['frontend_topology'] ?? null,
            ]);
        }

        return $this->success('companion-export', $bundle);
    }
}
