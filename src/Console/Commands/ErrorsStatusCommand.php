<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\BoogleService;
use Larapilot\Support\LarapilotCommand;

class ErrorsStatusCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:errors-status';

    /**
     * The name it had when Boogle was the only tracker.
     *
     * @var list<string>
     */
    protected $aliases = ['larapilot:boogle-status'];

    protected $description = 'Probe the production errors integration (setting, tracker, credentials, project)';

    public function handle(BoogleService $boogle): int
    {
        return $this->success('errors_status', $boogle->status());
    }
}
