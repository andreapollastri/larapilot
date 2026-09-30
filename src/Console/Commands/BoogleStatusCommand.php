<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\BoogleService;
use Larapilot\Support\LarapilotCommand;

class BoogleStatusCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:boogle-status';

    protected $description = 'Probe the production errors integration (setting, tracker, credentials, project)';

    public function handle(BoogleService $boogle): int
    {
        return $this->success('boogle_status', $boogle->status());
    }
}
