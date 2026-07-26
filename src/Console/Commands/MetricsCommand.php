<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\PlanService;
use Larapilot\Services\SpecService;
use Larapilot\Support\LarapilotCommand;

class MetricsCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:metrics
                            {--human : Print a readable table instead of the JSON envelope}';

    protected $description = 'Report backlog and plan progress metrics';

    public function handle(SpecService $specs, PlanService $plans): int
    {
        $metrics = array_merge($specs->metrics(), $plans->metrics());

        if ((bool) $this->option('human')) {
            $this->table(['Metric', 'Value'], collect($metrics)
                ->map(fn (mixed $value, string $key): array => [
                    $key,
                    is_array($value) ? json_encode($value) : (string) $value,
                ])
                ->values()
                ->all());

            return self::SUCCESS;
        }

        return $this->success('metrics', $metrics);
    }
}
