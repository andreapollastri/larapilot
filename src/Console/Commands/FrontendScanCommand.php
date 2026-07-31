<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\FrontendService;
use Larapilot\Support\LarapilotCommand;

class FrontendScanCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:frontend-scan
                            {--path= : Scan this absolute path instead of the configured frontend repo}';

    protected $description = 'Scan the external frontend repository structure and detect stack/tooling';

    public function handle(FrontendService $frontend): int
    {
        $path = $this->option('path');
        $scanPath = is_string($path) && trim($path) !== '' ? trim($path) : null;

        if ($scanPath !== null) {
            $validation = $frontend->validatePath($scanPath);

            if (! $validation['valid']) {
                return $this->failure(
                    'E_INVALID_INPUT',
                    'Invalid frontend repo path.',
                    $this->exitForCode('E_INVALID_INPUT'),
                    implode(' ', $validation['errors'])
                );
            }
        }

        $scan = $frontend->scan($scanPath);

        if (($scan['ok'] ?? false) !== true) {
            return $this->failure(
                'E_PRECONDITION',
                (string) ($scan['error'] ?? 'Frontend scan failed.'),
                $this->exitForCode('E_PRECONDITION'),
                is_array($scan['errors'] ?? null) ? implode(' ', $scan['errors']) : null
            );
        }

        return $this->success('frontend-scan', $scan);
    }
}
