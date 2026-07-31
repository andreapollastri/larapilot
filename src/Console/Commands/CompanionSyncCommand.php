<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\FrontendService;
use Larapilot\Support\LarapilotCommand;

class CompanionSyncCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:companion-sync';

    protected $description = 'Push the shared PRD and product OpenAPI mirror into the configured external frontend repo';

    public function handle(FrontendService $frontend): int
    {
        $result = $frontend->syncCompanion();

        if (($result['ok'] ?? false) !== true) {
            return $this->failure(
                'E_PRECONDITION',
                (string) ($result['error'] ?? 'Companion sync failed.'),
                $this->exitForCode('E_PRECONDITION')
            );
        }

        return $this->success('companion-sync', $result);
    }
}
