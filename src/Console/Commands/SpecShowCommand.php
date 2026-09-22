<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\SpecService;
use Larapilot\Support\LarapilotCommand;

class SpecShowCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:spec-show
                            {code : Spec code, e.g. US-001}
                            {--task= : Return only this task id, e.g. TASK-01}
                            {--fields= : Comma-separated task keys to keep (id is always kept)}';

    protected $description = 'Show one spec and its tasks';

    public function handle(SpecService $specs): int
    {
        $code = (string) $this->argument('code');
        $data = $specs->show($code);

        if ($data === null) {
            return $this->failure('E_NOT_FOUND', "Spec {$code} not found.", $this->exitForCode('E_NOT_FOUND'));
        }

        $task = $this->option('task');
        $sliced = $specs->slice($data, is_string($task) ? $task : null, $this->option('fields') !== null ? (string) $this->option('fields') : null);

        if ($sliced === null) {
            return $this->failure(
                'E_NOT_FOUND',
                "Task {$task} not found on {$code}.",
                $this->exitForCode('E_NOT_FOUND')
            );
        }

        return $this->success('spec_detail', $sliced);
    }
}
