<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\Tracker\TrackerException;
use Larapilot\Services\TrackerService;
use Larapilot\Support\LarapilotCommand;

class TrackerStatusCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:tracker-status
                            {--ping : Also call the provider to verify credentials and the target board/project}';

    protected $description = 'Show the configured project tracker, its status map, and which specs are linked';

    public function handle(TrackerService $tracker): int
    {
        try {
            return $this->success('tracker-status', $tracker->status((bool) $this->option('ping')));
        } catch (TrackerException $exception) {
            return $this->failure(
                'E_CONNECTOR',
                $exception->getMessage(),
                $this->exitForCode('E_CONNECTOR')
            );
        }
    }
}
