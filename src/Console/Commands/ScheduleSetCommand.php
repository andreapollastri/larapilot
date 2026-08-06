<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\UsageService;
use Larapilot\Support\LarapilotCommand;

class ScheduleSetCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:schedule-set
                            {--deadline= : YYYY-MM-DD delivery date}
                            {--label=Deadline : Milestone label}
                            {--status=on_track : on_track|at_risk|delayed|done}
                            {--note= : Note for the deadline or a free-standing schedule note}
                            {--note-only : Record a schedule note without a deadline}';

    protected $description = 'Record Lucille deadlines or schedule drift notes';

    public function handle(UsageService $usage): int
    {
        try {
            if ((bool) $this->option('note-only')) {
                $note = $usage->addScheduleNote([
                    'status' => $this->option('status'),
                    'note' => $this->option('note'),
                ]);

                return $this->success('schedule_note', [
                    'note' => $note,
                    'schedule' => $usage->schedule(),
                ]);
            }

            if (! $this->option('deadline')) {
                return $this->failure(
                    'E_INVALID_INPUT',
                    'Provide --deadline=YYYY-MM-DD or --note-only with --note=.',
                    $this->exitForCode('E_INVALID_INPUT')
                );
            }

            $deadline = $usage->setDeadline([
                'deadline' => $this->option('deadline'),
                'label' => $this->option('label'),
                'status' => $this->option('status'),
                'note' => $this->option('note'),
            ]);

            return $this->success('schedule_deadline', [
                'deadline' => $deadline,
                'schedule' => $usage->schedule(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->failure(
                'E_INVALID_INPUT',
                $e->getMessage(),
                $this->exitForCode('E_INVALID_INPUT')
            );
        }
    }
}
