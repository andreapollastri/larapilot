<?php

declare(strict_types=1);

namespace Larapilot\Services;

/**
 * Aggregated delivery metrics for the `/larapilot/api/metrics` endpoint and
 * the `larapilot:metrics` command — backlog + plan progress, plus a lean
 * effort-timing block derived from the Lucille usage ledger when it is on.
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
