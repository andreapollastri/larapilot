<?php

declare(strict_types=1);

namespace Larapilot\Services;

/**
 * Aggregated delivery metrics for the `/larapilot/api/metrics` endpoint and
 * the `larapilot:metrics` command — backlog + plan progress, plus a lean
 * effort-timing block derived from the Lucille usage ledger when it is on,
 * and what the delivered specs took to build against their estimates.
 */
class MetricsService
{
    public function __construct(
        protected ConfigService $config,
        protected SpecService $specs,
        protected PlanService $plans,
        protected UsageService $usage,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'collected_at' => now()->toIso8601String(),
            'backlog' => $this->specs->metrics(),
            'plan' => $this->plans->metrics(),
            'delivery' => $this->deliveryTiming(),
            'build' => $this->buildTiming(),
        ];
    }

    /**
     * Flat map for the legacy `larapilot:metrics` envelope.
     *
     * @return array<string, mixed>
     */
    public function flat(): array
    {
        return array_merge($this->specs->metrics(), $this->plans->metrics());
    }

    /**
     * The delivered specs: their estimates beside the time they spent in
     * progress. It is read from the backlog, so it is there with Lucille off.
     *
     * @return array<string, mixed>|null
     */
    protected function buildTiming(): ?array
    {
        try {
            $totals = $this->usage->actuals()['totals'];
        } catch (\Throwable) {
            return null;
        }

        return [
            'specs_delivered' => (int) $totals['delivered'],
            'specs_timed' => (int) $totals['timed'],
            'estimate_hours' => (float) $totals['estimate_hours'],
            'build_hours' => (float) $totals['build_hours'],
            'estimate_to_build' => $totals['ratio'],
            'review_wait_hours' => (float) $totals['review_hours'],
            'reworks' => (int) $totals['reworks'],
            'restarts' => (int) $totals['restarts'],
            'tokens' => (int) $totals['tokens'],
            'specs_with_tokens' => (int) $totals['specs_with_tokens'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function deliveryTiming(): ?array
    {
        if (! $this->config->lucilleEnabled()) {
            return null;
        }

        try {
            $summary = $this->usage->summary();
        } catch (\Throwable) {
            return null;
        }

        $byDay = is_array($summary['by_day'] ?? null) ? $summary['by_day'] : [];
        $days = array_keys($byDay);

        return [
            'tracked_entries' => (int) ($summary['entry_count'] ?? 0),
            'total_hours' => (float) ($summary['total_hours'] ?? 0.0),
            'total_tokens' => (int) ($summary['total_tokens'] ?? 0),
            'estimated_entries' => (int) ($summary['estimated_entry_count'] ?? 0),
            'specs_tracked' => count(is_array($summary['by_spec'] ?? null) ? $summary['by_spec'] : []),
            'first_activity' => $days === [] ? null : (string) reset($days),
            'last_activity' => $days === [] ? null : (string) end($days),
        ];
    }
}
