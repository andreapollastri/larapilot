<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\SpecService;
use Larapilot\Support\LarapilotCommand;

class MetricsCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:metrics';

    protected $description = 'Report backlog progress metrics';

    public function handle(SpecService $specs): int
    {
        return $this->success('metrics', $specs->metrics());
    }
}
