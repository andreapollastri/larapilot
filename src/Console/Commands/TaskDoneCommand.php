<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\PlanService;
use Larapilot\Support\LarapilotCommand;

class TaskDoneCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:task-done
                            {code : Spec code}
                            {taskId : Task id, e.g. TASK-01}';

    protected $description = 'Mark one plan task as completed';

    public function handle(PlanService $plans, string $code, string $taskId): int
    {
        try {
            $plans->markTaskDone($code, $taskId);
        } catch (\RuntimeException $exception) {
            return $this->failure('E_NOT_FOUND', $exception->getMessage(), $this->exitForCode('E_NOT_FOUND'));
        }

        return $this->success('task_done_result', [
            'code' => $code,
            'task_id' => $taskId,
            'status' => 'DONE',
        ]);
    }
}
