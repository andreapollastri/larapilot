<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ScheduleService;
use Larapilot\Support\LarapilotCommand;
use Larapilot\Support\PayloadFile;

class ScheduleApplyCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:schedule-apply
                            {--file= : YAML or JSON file with the re-plan: specs, tasks, epics, deadlines, note}
                            {--repair : No file: put right what takes no decision — milestone ids and dates, a milestone named after a release, an epic deadline only some of its specs carry}
                            {--dry-run : Forecast the re-plan, or list the repairs, and write nothing}';

    protected $description = 'Re-plan the delivery in one batch: priorities, points, blockers, task estimates and assignees, epic deadlines, milestones';

    protected bool $refreshesEconomics = true;

    public function handle(ScheduleService $schedule): int
    {
        if ((bool) $this->option('repair')) {
            if (is_string($this->option('file')) && $this->option('file') !== '') {
                return $this->failure('E_INVALID_INPUT', '--repair takes no --file: run the repair, then apply the re-plan.', $this->exitForCode('E_INVALID_INPUT'));
            }

            return $this->success('schedule_repair', $schedule->repair((bool) $this->option('dry-run')));
        }

        $file = $this->option('file');

        if (! is_string($file) || ! is_file($file)) {
            return $this->failure('E_INVALID_INPUT', 'A valid --file path is required.', $this->exitForCode('E_INVALID_INPUT'));
        }

        $payload = PayloadFile::parse($file);

        if ($payload === null) {
            return $this->failure('E_INVALID_INPUT', 'Invalid re-plan payload.', $this->exitForCode('E_INVALID_INPUT'));
        }

        $dryRun = (bool) $this->option('dry-run');
        $result = $schedule->apply($payload, $dryRun);

        if (! $result['ok']) {
            return $this->failure(
                'E_INVALID_INPUT',
                'The re-plan failed validation: nothing was written.',
                $this->exitForCode('E_INVALID_INPUT'),
                'Fix the findings and retry.',
                ['findings' => $result['findings']]
            );
        }

        unset($result['ok']);

        // A forecast changes no input of the quote.
        $this->refreshesEconomics = ! $dryRun;

        return $this->success('schedule_apply', $result);
    }
}
