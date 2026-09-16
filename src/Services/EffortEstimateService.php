<?php

declare(strict_types=1);

namespace Larapilot\Services;

/**
 * AI-assisted delivery estimates per spec — story points plus an hourly
 * breakdown (plan, implement, review, rework/fix buffer, deploy).
 */
class EffortEstimateService
{
    /**
     * @return array{
     *     plan: float,
     *     implement: float,
     *     review: float,
     *     rework: float,
     *     deploy: float,
     *     total: float,
     *     source: string,
     *     label_total: string,
     *     label_breakdown: string
     * }
     */
    public function forSpec(array $spec, ?array $plan = null): array
    {
        $explicit = $this->explicitPhases($spec);

        if ($explicit !== null) {
            return $this->finalize($explicit, 'explicit', (bool) ($spec['rework'] ?? false));
        }

        $points = max(0, (int) ($spec['points'] ?? 0));
        $taskHours = $this->taskImplementHours($plan);
        $ratios = $this->phaseRatios();
        $hoursPerPoint = $this->hoursPerPoint();

        if ($points === 0 && $taskHours <= 0) {
            return $this->emptyEstimate();
        }

        $baseTotal = $points > 0 ? max(2.0, $points * $hoursPerPoint) : 0.0;
        $implementRatio = max(0.01, $ratios['implement']);

        if ($taskHours > 0) {
            $implement = $taskHours;
            $total = max($baseTotal, $implement / $implementRatio);
            $source = 'tasks';
        } else {
            $total = $baseTotal;
            $implement = $total * $implementRatio;
            $source = 'points';
        }

        $phases = [
            'plan' => $total * $ratios['plan'],
            'implement' => $implement,
            'review' => $total * $ratios['review'],
            'rework' => $total * $ratios['rework'],
            'deploy' => $total * $ratios['deploy'],
        ];

        return $this->finalize($phases, $source, (bool) ($spec['rework'] ?? false));
    }

    /**
     * @param  list<array<string, mixed>>  $specs
     * @return array{plan: float, implement: float, review: float, rework: float, deploy: float, total: float, label_total: string}
     */
    public function sumSpecs(array $specs): array
    {
        $totals = [
            'plan' => 0.0,
            'implement' => 0.0,
            'review' => 0.0,
            'rework' => 0.0,
            'deploy' => 0.0,
            'total' => 0.0,
        ];

        foreach ($specs as $spec) {
            if (! is_array($spec)) {
                continue;
            }

            $estimate = is_array($spec['estimate'] ?? null)
                ? $spec['estimate']
                : $this->forSpec($spec);

            foreach (array_keys($totals) as $key) {
                $totals[$key] += (float) ($estimate[$key] ?? 0);
            }
        }

        foreach ($totals as $key => $value) {
            $totals[$key] = round($value, 1);
        }

        $totals['label_total'] = $this->formatHours($totals['total']);

        return $totals;
    }

    /**
     * @param  list<array<string, mixed>>  $specs
     * @param  string  $doneStatus
     * @return array{total: float, remaining: float, done: float, label_total: string, label_remaining: string}
     */
    public function backlogHours(array $specs, string $doneStatus = 'DONE'): array
    {
        $total = 0.0;
        $remaining = 0.0;

        foreach ($specs as $spec) {
            if (! is_array($spec)) {
                continue;
            }

            $estimate = is_array($spec['estimate'] ?? null)
                ? $spec['estimate']
                : $this->forSpec($spec);

            $hours = (float) ($estimate['total'] ?? 0);
            $total += $hours;

            if (strtoupper((string) ($spec['status'] ?? '')) !== strtoupper($doneStatus)) {
                $remaining += $hours;
            }
        }

        $total = round($total, 1);
        $remaining = round($remaining, 1);

        return [
            'total' => $total,
            'remaining' => $remaining,
            'done' => round(max(0, $total - $remaining), 1),
            'label_total' => $this->formatHours($total),
            'label_remaining' => $this->formatHours($remaining),
        ];
    }

    public function formatHours(float $hours): string
    {
        if ($hours <= 0) {
            return '0h';
        }

        $rounded = round($hours, 1);

        if (abs($rounded - round($rounded)) < 0.05) {
            return ((int) round($rounded)).'h';
        }

        return rtrim(rtrim(number_format($rounded, 1, '.', ''), '0'), '.').'h';
    }

    /**
     * @return array{plan: float, implement: float, review: float, rework: float, deploy: float}|null
     */
    protected function explicitPhases(array $spec): ?array
    {
        $raw = $spec['estimate_hours'] ?? null;

        if (! is_array($raw)) {
            return null;
        }

        $phases = [];

        foreach (['plan', 'implement', 'review', 'rework', 'deploy'] as $phase) {
            if (! array_key_exists($phase, $raw)) {
                continue;
            }

            $phases[$phase] = max(0.0, (float) $raw[$phase]);
        }

        if ($phases === []) {
            return null;
        }

        foreach (['plan', 'implement', 'review', 'rework', 'deploy'] as $phase) {
            $phases[$phase] ??= 0.0;
        }

        return $phases;
    }

    /**
     * @param  array<string, mixed>|null  $plan
     */
    protected function taskImplementHours(?array $plan): float
    {
        if ($plan === null) {
            return 0.0;
        }

        $tasks = is_array($plan['tasks'] ?? null) ? $plan['tasks'] : [];
        $hours = 0.0;

        foreach ($tasks as $task) {
            if (! is_array($task)) {
                continue;
            }

            if (array_key_exists('estimate_hours', $task)) {
                $hours += max(0.0, (float) $task['estimate_hours']);
            }
        }

        return $hours;
    }

    /**
     * @return array{plan: float, implement: float, review: float, rework: float, deploy: float}
     */
    protected function phaseRatios(): array
    {
        $configured = config('larapilot.estimate.phase_ratios', []);
        $defaults = [
            'plan' => 0.15,
            'implement' => 0.55,
            'review' => 0.10,
            'rework' => 0.12,
            'deploy' => 0.08,
        ];

        $ratios = [];

        foreach ($defaults as $phase => $default) {
            $ratios[$phase] = max(0.0, (float) ($configured[$phase] ?? $default));
        }

        $sum = array_sum($ratios);

        if ($sum <= 0) {
            return $defaults;
        }

        foreach ($ratios as $phase => $value) {
            $ratios[$phase] = $value / $sum;
        }

        return $ratios;
    }

    protected function hoursPerPoint(): float
    {
        return max(0.5, (float) config('larapilot.estimate.hours_per_point', 4));
    }

    protected function reworkMultiplier(): float
    {
        return max(1.0, (float) config('larapilot.estimate.rework_multiplier', 1.5));
    }

    /**
     * @param  array{plan: float, implement: float, review: float, rework: float, deploy: float}  $phases
     * @return array<string, mixed>
     */
    protected function finalize(array $phases, string $source, bool $reworkSpec): array
    {
        if ($reworkSpec && $source !== 'explicit') {
            $phases['rework'] *= $this->reworkMultiplier();
        }

        foreach ($phases as $phase => $value) {
            $phases[$phase] = round(max(0.0, $value), 1);
        }

        $phases['total'] = round(array_sum($phases), 1);

        return array_merge($phases, [
            'source' => $source,
            'label_total' => $this->formatHours($phases['total']),
            'label_breakdown' => $this->breakdownLabel($phases),
        ]);
    }

    /**
     * @param  array{plan: float, implement: float, review: float, rework: float, deploy: float, total?: float}  $phases
     */
    protected function breakdownLabel(array $phases): string
    {
        $parts = [];

        foreach ([
            'plan' => 'Plan',
            'implement' => 'Impl',
            'review' => 'Review',
            'rework' => 'Rework',
            'deploy' => 'Deploy',
        ] as $key => $label) {
            $value = (float) ($phases[$key] ?? 0);

            if ($value <= 0) {
                continue;
            }

            $parts[] = $label.' '.$this->formatHours($value);
        }

        return $parts === [] ? '' : implode(' · ', $parts);
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyEstimate(): array
    {
        return [
            'plan' => 0.0,
            'implement' => 0.0,
            'review' => 0.0,
            'rework' => 0.0,
            'deploy' => 0.0,
            'total' => 0.0,
            'source' => 'none',
            'label_total' => '0h',
            'label_breakdown' => '',
        ];
    }
}
