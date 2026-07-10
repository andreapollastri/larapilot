<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\PlanService;
use Larapilot\Support\LarapilotCommand;

class TaskDoneCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:task-done
                            {code : Spec code}
                            {taskId : Task id, e.g. TASK-01}
                            {--commit= : Optional git commit SHA to link (auto-detected from recent history when omitted)}';

    protected $description = 'Mark one plan task as completed';

    public function handle(PlanService $plans): int
    {
        $code = (string) $this->argument('code');
        $taskId = (string) $this->argument('taskId');
        $commitOption = $this->option('commit');
        $commitSha = is_string($commitOption) && $commitOption !== '' ? $commitOption : null;

        try {
            $commit = $plans->markTaskDone($code, $taskId, $commitSha);
        } catch (\RuntimeException $exception) {
            return $this->failure('E_NOT_FOUND', $exception->getMessage(), $this->exitForCode('E_NOT_FOUND'));
        }

        return $this->success('task_done_result', [
            'code' => $code,
            'task_id' => $taskId,
            'status' => 'DONE',
            'commit' => $commit,
        ]);
    }
}
