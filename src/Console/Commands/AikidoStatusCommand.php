<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\AikidoService;
use Larapilot\Support\LarapilotCommand;

class AikidoStatusCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:aikido-status';

    protected $description = 'Probe the optional Aikido integration (setting, credentials, repository)';

    public function handle(AikidoService $aikido): int
    {
        return $this->success('aikido_status', $aikido->status());
    }
}
