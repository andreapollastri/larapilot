<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\AzureDevopsService;
use Larapilot\Support\LarapilotCommand;

class AzureDevopsStatusCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:azure-status';

    protected $description = 'Probe optional Azure DevOps integration (az CLI, azure-devops extension, auth, origin remote)';

    public function handle(AzureDevopsService $azure): int
    {
        return $this->success('azure_status', $azure->status());
    }
}
