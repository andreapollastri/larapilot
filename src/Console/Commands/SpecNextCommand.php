<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\SpecService;
use Larapilot\Support\LarapilotCommand;

class SpecNextCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:spec-next
                            {--status= : Status filter (defaults to TODO)}
                            {--task= : Return only this task id, e.g. TASK-01}
                            {--fields= : Comma-separated task keys to keep (id is always kept)}';

    protected $description = 'Auto-select the first eligible spec by priority and code';

    public function handle(SpecService $specs, ConfigService $config): int
    {
        $status = $this->option('status') ?? $config->status('todo');
        $data = $specs->next($status);

        if ($data === null) {
            return $this->failure(
                'E_PRECONDITION',
                "No eligible specs found for status {$status}.",
                $this->exitForCode('E_PRECONDITION'),
                'Create specs with larapilot-spec or add items via larapilot:spec-add.'
            );
        }

        $task = $this->option('task');
        $sliced = $specs->slice($data, is_string($task) ? $task : null, $this->option('fields') !== null ? (string) $this->option('fields') : null);

        if ($sliced === null) {
            return $this->failure(
                'E_NOT_FOUND',
                "Task {$task} not found on ".($data['spec']['code'] ?? 'spec').'.',
                $this->exitForCode('E_NOT_FOUND')
            );
        }

        return $this->success('spec_detail', $sliced);
    }
}
