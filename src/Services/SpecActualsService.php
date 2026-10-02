<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\EffortEstimate;

/**
 * What every delivered spec took, beside what it was estimated at.
 *
 * The time is read from `status_history`: `spec-start`, `spec-review`, and
 * `spec-approve` already stamp the backlog, so nothing more is asked of the
 * agent. Build time is the time a spec spent IN PROGRESS — pauses included,
 * since the history cannot tell work from waiting. The estimate is the one
 * Economics prices, before the PM/QA buffer.
 */
class SpecActualsService
{
    /**
     * Timed specs it takes before a ratio is given for the whole project.
     */
    public const MIN_TIMED_SPECS = 3;

    public function __construct(
        protected ConfigService $config,
        protected SpecService $specs,
        protected PlanService $plans,
    ) {}

    /**
     * @param  array<string, array{entries?: int, tokens?: int, minutes?: float}>  $ledgerBySpec  The `by_spec` buckets of the usage ledger.
     * @return array{specs: list<array<string, mixed>>, totals: array<string, mixed>, statuses: array{in_progress: string, review: string, done: string}}
     */
    public function snapshot(array $ledgerBySpec = []): array
    {
        $ledger = [];

        foreach ($ledgerBySpec as $code => $bucket) {
            $ledger[strtoupper(trim((string) $code))] = $bucket;
        }

        $estimates = $this->estimates();
        $doneStatus = strtoupper($this->config->status('done'));
        $rows = [];

        foreach ($this->specs->allSpecs() as $spec) {
            $code = is_array($spec) ? trim((string) ($spec['code'] ?? '')) : '';

            if ($code === '' || strtoupper(trim((string) ($spec['status'] ?? ''))) !== $doneStatus) {
                continue;
            }

            $timing = $this->timing(is_array($spec['status_history'] ?? null) ? $spec['status_history'] : []);
            $estimate = $estimates['specs'][$code] ?? ['hours' => 0.0, 'from' => 'unsized'];
            $logged = $ledger[strtoupper($code)] ?? null;
            $buildMinutes = $timing['timed'] ? round($timing['build_seconds'] / 60, 1) : null;
            $reviewMinutes = $timing['reviewed'] ? round($timing['review_seconds'] / 60, 1) : null;
            // Under a minute the spec was moved, not built: no ratio to read.
            $ratio = $buildMinutes !== null && $buildMinutes >= 1 && $estimate['hours'] > 0
                ? round($estimate['hours'] * 60 / $buildMinutes, 1)
                : null;

            $rows[] = [
                'code' => $code,
                'title' => (string) ($spec['title'] ?? $code),
                'estimate_hours' => round($estimate['hours'], 1),
                'estimate_from' => $estimate['from'],
                'timed' => $timing['timed'],
                'build_minutes' => $buildMinutes,
                'build_hours' => $buildMinutes !== null ? round($buildMinutes / 60, 2) : null,
                'build_display' => $buildMinutes !== null ? self::duration($buildMinutes) : null,
                'ratio' => $ratio,
                'ratio_display' => $ratio !== null ? self::ratio($ratio) : null,
                'review_minutes' => $reviewMinutes,
                'review_display' => $reviewMinutes !== null ? self::duration($reviewMinutes) : null,
                'reworks' => $timing['reworks'],
                'restarts' => $timing['restarts'],
                'tokens' => $logged !== null ? (int) ($logged['tokens'] ?? 0) : null,
                'logged_minutes' => $logged !== null ? round((float) ($logged['minutes'] ?? 0), 1) : null,
                'started_at' => $timing['started_at'],
                'delivered_at' => $timing['delivered_at'],
            ];
        }

        // The newest delivery first; a spec with no history has no date and closes the list.
        usort($rows, static function (array $a, array $b): int {
            if (($a['delivered_at'] === null) !== ($b['delivered_at'] === null)) {
                return $a['delivered_at'] === null ? 1 : -1;
            }

            return ($b['delivered_at'] <=> $a['delivered_at']) ?: strnatcasecmp($a['code'], $b['code']);
        });

        return [
            'specs' => $rows,
            'totals' => $this->totals($rows, count($estimates['specs'])) + [
                'hours_per_point' => $estimates['hours_per_point'],
                'hours_per_point_source' => $estimates['hours_per_point_source'],
            ],
            // The names the project gives the steps the times are read between.
            'statuses' => [
                'in_progress' => $this->config->status('in_progress'),
                'review' => $this->config->status('review'),
                'done' => $this->config->status('done'),
            ],
        ];
    }

    /**
     * Minutes as a person reads them: `51 min`, `1 h 37 min`, `124 h`.
     */
    public static function duration(float $minutes): string
    {
        $minutes = (int) round(max(0.0, $minutes));

        if ($minutes < 60) {
            return $minutes < 1 ? '< 1 min' : $minutes.' min';
        }

        if ($minutes >= 6000 || $minutes % 60 === 0) {
            return ((int) round($minutes / 60)).' h';
        }

        return intdiv($minutes, 60).' h '.($minutes % 60).' min';
    }

    /**
     * How many times the estimate holds the build: `28×`, `2.2×`.
     */
    public static function ratio(float $ratio): string
    {
        return ($ratio >= 10 ? (string) (int) round($ratio) : rtrim(rtrim(number_format($ratio, 1, '.', ''), '0'), '.')).'×';
    }

    /**
     * The estimate of every spec, by the rule Economics prices the backlog with.
     *
     * @return array{specs: array<string, array{hours: float, from: string}>, hours_per_point: float, hours_per_point_source: string}
     */
    public function estimates(): array
    {
        $sized = [];
        $plannedHours = 0.0;
        $plannedPoints = 0;
        $plannedSpecs = 0;

        foreach ($this->specs->allSpecs() as $spec) {
            $code = is_array($spec) ? trim((string) ($spec['code'] ?? '')) : '';

            if ($code === '') {
                continue;
            }

            $planHours = 0.0;
            $plan = $this->plans->read($code);

            foreach (is_array($plan['tasks'] ?? null) ? $plan['tasks'] : [] as $task) {
                if (is_array($task)) {
                    $planHours += max(0.0, (float) ($task['estimate_hours'] ?? 0));
                }
            }

            $planHours = round($planHours, 1);
            $points = max(0, (int) ($spec['points'] ?? 0));
            $sized[$code] = [$planHours, $points];

            if ($planHours > 0) {
                $plannedHours += $planHours;
                $plannedPoints += $points;
                $plannedSpecs++;
            }
        }

        $calibrated = EffortEstimate::calibratedHoursPerPoint($plannedHours, $plannedPoints, $plannedSpecs);
        $hoursPerPoint = $calibrated
            ?? EffortEstimate::settingHoursPerPoint((string) ($this->config->settings()['effort'] ?? 'STANDARD'));

        return [
            'specs' => array_map(
                static fn (array $size): array => EffortEstimate::specHours($size[0], $size[1], $hoursPerPoint),
                $sized
            ),
            'hours_per_point' => $hoursPerPoint,
            'hours_per_point_source' => $calibrated !== null ? 'plans' : 'settings',
        ];
    }

    /**
     * The time between the statuses a spec went through. A step lasts until
     * the next one, so the last step of the history has no length.
     *
     * A build that ends anywhere but in review or done was started over; a
     * review that ends anywhere but done was sent back.
     *
     * @param  array<int, mixed>  $history
     * @return array{timed: bool, build_seconds: int, reviewed: bool, review_seconds: int, reworks: int, restarts: int, started_at: string|null, delivered_at: string|null}
     */
    protected function timing(array $history): array
    {
        $inProgress = strtoupper($this->config->status('in_progress'));
        $review = strtoupper($this->config->status('review'));
        $done = strtoupper($this->config->status('done'));
        $steps = [];

        foreach ($history as $entry) {
            $at = is_array($entry) ? strtotime((string) ($entry['at'] ?? '')) : false;

            if ($at !== false) {
                $steps[] = [strtoupper(trim((string) ($entry['status'] ?? ''))), $at];
            }
        }

        $timing = [
            'timed' => false,
            'build_seconds' => 0,
            'reviewed' => false,
            'review_seconds' => 0,
            'reworks' => 0,
            'restarts' => 0,
            'started_at' => null,
            'delivered_at' => null,
        ];

        foreach ($steps as $index => [$status, $at]) {
            if ($status === $done) {
                $timing['delivered_at'] = gmdate('c', $at);
            }

            $next = $steps[$index + 1] ?? null;

            if ($next === null) {
                continue;
            }

            $seconds = max(0, $next[1] - $at);

            if ($status === $inProgress) {
                $timing['timed'] = true;
                $timing['started_at'] ??= gmdate('c', $at);
                $timing['build_seconds'] += $seconds;

                if (! in_array($next[0], [$inProgress, $review, $done], true)) {
                    $timing['restarts']++;
                }
            } elseif ($status === $review) {
                $timing['reviewed'] = true;
                $timing['review_seconds'] += $seconds;

                if (! in_array($next[0], [$review, $done], true)) {
                    $timing['reworks']++;
                }
            }
        }

        return $timing;
    }

    /**
     * The comparison counts only the specs with a build time: an estimate
     * with nothing measured beside it would raise the total and say nothing.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    protected function totals(array $rows, int $backlog): array
    {
        $timed = 0;
        $estimate = 0.0;
        $buildMinutes = 0.0;
        $reviewMinutes = 0.0;
        $tokens = 0;
        $withTokens = 0;
        $reworks = 0;
        $restarts = 0;

        foreach ($rows as $row) {
            $reworks += $row['reworks'];
            $restarts += $row['restarts'];
            $reviewMinutes += (float) ($row['review_minutes'] ?? 0);

            if (($row['tokens'] ?? 0) > 0) {
                $tokens += $row['tokens'];
                $withTokens++;
            }

            if ($row['timed']) {
                $timed++;
                $estimate += $row['estimate_hours'];
                $buildMinutes += $row['build_minutes'];
            }
        }

        $ratio = $timed >= self::MIN_TIMED_SPECS && $buildMinutes >= 1 && $estimate > 0
            ? round($estimate * 60 / $buildMinutes, 1)
            : null;

        return [
            'backlog' => $backlog,
            'delivered' => count($rows),
            'timed' => $timed,
            'min_timed_specs' => self::MIN_TIMED_SPECS,
            'estimate_hours' => round($estimate, 1),
            'build_hours' => round($buildMinutes / 60, 1),
            'build_display' => self::duration($buildMinutes),
            'ratio' => $ratio,
            'ratio_display' => $ratio !== null ? self::ratio($ratio) : null,
            'review_hours' => round($reviewMinutes / 60, 1),
            'review_display' => self::duration($reviewMinutes),
            'tokens' => $tokens,
            'specs_with_tokens' => $withTokens,
            'reworks' => $reworks,
            'restarts' => $restarts,
        ];
    }
}
