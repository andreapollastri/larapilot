<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\BoogleService;
use Larapilot\Support\LarapilotCommand;

class BoogleStatusCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:boogle-status';

    protected $description = 'Probe the optional Boogle integration (setting, address, token, project)';

    public function handle(BoogleService $boogle): int
    {
        return $this->success('boogle_status', $boogle->status());
    }
}
