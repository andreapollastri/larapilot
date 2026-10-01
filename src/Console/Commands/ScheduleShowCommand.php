<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ScheduleService;
use Larapilot\Support\LarapilotCommand;

class ScheduleShowCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:schedule-show
                            {--only= : Parts to keep, comma-separated: queue, epics, deadlines, releases, alerts, findings}';

    protected $description = 'The delivery forecast: open specs in the order they are delivered, every date against it, and what its inputs lack';

    public function handle(ScheduleService $schedule): int
    {
        $only = array_values(array_filter(array_map(
            'trim',
            explode(',', strtolower((string) $this->option('only')))
        ), static fn (string $part): bool => $part !== ''));

        $unknown = array_diff($only, ScheduleService::PARTS);

        if ($unknown !== []) {
            return $this->failure(
                'E_INVALID_INPUT',
                'Unknown part: '.implode(', ', $unknown).'.',
                $this->exitForCode('E_INVALID_INPUT'),
                'Parts: '.implode(', ', ScheduleService::PARTS).'.'
            );
        }

        $data = $schedule->show();

        if ($only !== []) {
            $data = array_diff_key($data, array_flip(array_diff(ScheduleService::PARTS, $only)));
        }

        return $this->success('schedule', $data);
    }
}
