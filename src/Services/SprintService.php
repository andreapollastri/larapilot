<?php

declare(strict_types=1);

namespace Larapilot\Services;

use DateTimeImmutable;
use Larapilot\Support\EffortEstimate;

/**
 * The work cut into sprints of two weeks: the sprint a spec carries in the
 * backlog when somebody decided one, and the sprint it is proposed for when
 * nobody did — the open specs laid one after another, in the order they are
 * delivered, into what one person does in a sprint.
 *
 * The capacity is the one the Forecast page counts with: the hours of a
 * sprint over the hours a story point stands for. Nothing is computed twice.
 */
class SprintService
{
    /**
     * A sprint is two weeks, calendar days: the band on the Plan.
     */
    public const LENGTH_DAYS = 14;

    public function __construct(
        protected ConfigService $config,
        protected ForecastService $forecast,
        protected UsageService $usage,
    ) {}

    /**
     * Whether the Board, the Plan, and the skills split the work into sprints.
     */
    public function enabled(): bool
    {
        return $this->config->sprintsEnabled();
    }

    /**
     * The day sprint 1 begins: the first day the ledger recorded work on
     * the project, today when it has none yet.
     */
    public function origin(): string
    {
        $first = null;

        foreach ($this->usage->entries() as $entry) {
            $day = substr((string) ($entry['ts'] ?? ''), 0, 10);

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1 && ($first === null || $day < $first)) {
                $first = $day;
            }
        }

        return $first ?? (new DateTimeImmutable('today'))->format('Y-m-d');
    }

    /**
     * What one person delivers in a sprint, in the units of the Forecast:
     * its hours, the hours a story point stands for, and the points that
     * gives.
     *
     * @return array{hours: float, hours_per_point: float, points: float}
     */
    public function capacity(): array
    {
        $hours = (float) $this->forecast->sprint()['hours'];
        $perPoint = (float) $this->forecast->hoursPerPoint()['value'];

        return [
            'hours' => $hours,
            'hours_per_point' => $perPoint,
            'points' => $perPoint > 0 ? round($hours / $perPoint, 1) : 0.0,
        ];
    }

    /**
     * The sprint a day falls in, counted from the origin; never under 1.
     */
    public function number(string $day, ?string $origin = null): int
    {
        $origin ??= $this->origin();
        $days = (new DateTimeImmutable($origin))->diff(new DateTimeImmutable($day))->days;

        if ($day < $origin) {
            return 1;
        }

        return intdiv((int) $days, self::LENGTH_DAYS) + 1;
    }

    /**
     * The sprint today falls in.
     */
    public function current(?string $origin = null): int
    {
        return $this->number((new DateTimeImmutable('today'))->format('Y-m-d'), $origin);
    }

    /**
     * The first and the last day of a sprint.
     *
     * @return array{start: string, end: string}
     */
    public function window(int $number, ?string $origin = null): array
    {
        $start = (new DateTimeImmutable($origin ?? $this->origin()))->modify('+'.(max(1, $number) - 1) * self::LENGTH_DAYS.' days');

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $start->modify('+'.(self::LENGTH_DAYS - 1).' days')->format('Y-m-d'),
        ];
    }

    /**
     * The sprint written on a spec, when it carries a valid one.
     *
     * @param  array<string, mixed>  $spec
     */
    public static function explicit(array $spec): ?int
    {
        $value = $spec['sprint'] ?? null;

        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1 && (int) $value > 0) {
            return (int) $value;
        }

        return null;
    }

    /**
     * Every spec with its sprint: the one written on it, or — when
     * `$propose` is on — the one it is proposed for. Open specs with no
     * sprint are laid from the current sprint onward, work in progress
     * first, then by priority and code as `spec-next` picks them, each
     * sprint taking what fits its capacity. A spec done before sprints were
     * planned is delivered, in no sprint.
     *
     * @param  list<array<string, mixed>>  $specs
     * @return array{
     *     origin: string,
     *     current: int,
     *     capacity: array{hours: float, hours_per_point: float, points: float},
     *     assignments: array<string, array{sprint: int|null, proposed: bool}>,
     *     sprints: list<array{number: int, start: string, end: string, current: bool, past: bool, specs: list<string>, points: int, proposed: int}>,
     *     delivered: list<string>
     * }
     */
    public function plan(array $specs, bool $propose = true): array
    {
        $origin = $this->origin();
        $current = $this->current($origin);
        $capacity = $this->capacity();
        $done = strtoupper($this->config->status('done'));
        $active = [strtoupper($this->config->status('in_progress')), strtoupper($this->config->status('review'))];

        $assignments = [];
        $used = [];
        $delivered = [];
        $open = [];

        foreach ($specs as $index => $spec) {
            $code = trim((string) ($spec['code'] ?? ''));

            if ($code === '') {
                continue;
            }

            $explicit = self::explicit($spec);

            if ($explicit !== null) {
                $assignments[$code] = ['sprint' => $explicit, 'proposed' => false];
                $used[$explicit] = ($used[$explicit] ?? 0.0) + $this->size($spec);

                continue;
            }

            $status = strtoupper(trim((string) ($spec['status'] ?? '')));

            if ($status === $done) {
                $assignments[$code] = ['sprint' => null, 'proposed' => false];
                $delivered[] = $code;

                continue;
            }

            $assignments[$code] = ['sprint' => null, 'proposed' => false];
            $open[] = [
                'code' => $code,
                'size' => $this->size($spec),
                'stage' => in_array($status, $active, true) ? 0 : 1,
                'priority' => match (strtoupper(trim((string) ($spec['priority'] ?? 'MEDIUM')))) {
                    'CRITICAL' => 0,
                    'HIGH' => 1,
                    'MEDIUM' => 2,
                    'LOW' => 3,
                    default => 2,
                },
                'index' => (int) $index,
            ];
        }

        if ($propose) {
            usort($open, static fn (array $a, array $b): int => [$a['stage'], $a['priority'], $a['index']] <=> [$b['stage'], $b['priority'], $b['index']]);

            $number = $current;

            foreach ($open as $row) {
                // A sprint takes what fits; a spec too large for an empty sprint takes it alone.
                while ($capacity['points'] > 0) {
                    $taken = $used[$number] ?? 0.0;

                    if ($taken <= 0 || $taken + $row['size'] <= $capacity['points']) {
                        break;
                    }

                    $number++;
                }

                $assignments[$row['code']] = ['sprint' => $number, 'proposed' => true];
                $used[$number] = ($used[$number] ?? 0.0) + $row['size'];
            }
        }

        $sprints = [];

        foreach ($assignments as $code => $assignment) {
            if ($assignment['sprint'] === null) {
                continue;
            }

            $n = $assignment['sprint'];
            $sprints[$n] ??= $this->emptySprint($n, $origin, $current);
            $sprints[$n]['specs'][] = (string) $code;
            $sprints[$n]['proposed'] += $assignment['proposed'] ? 1 : 0;
        }

        // The current sprint is on the page even when nothing is in it yet.
        $sprints[$current] ??= $this->emptySprint($current, $origin, $current);

        $points = [];

        foreach ($specs as $spec) {
            $points[(string) ($spec['code'] ?? '')] = max(0, (int) ($spec['points'] ?? 0));
        }

        foreach ($sprints as &$sprint) {
            $sprint['points'] = array_sum(array_map(static fn (string $code): int => $points[$code] ?? 0, $sprint['specs']));
        }

        unset($sprint);
        ksort($sprints);

        return [
            'origin' => $origin,
            'current' => $current,
            'capacity' => $capacity,
            'assignments' => $assignments,
            'sprints' => array_values($sprints),
            'delivered' => $delivered,
        ];
    }

    /**
     * @return array{number: int, start: string, end: string, current: bool, past: bool, specs: list<string>, points: int, proposed: int}
     */
    protected function emptySprint(int $number, string $origin, int $current): array
    {
        return $this->window($number, $origin) + [
            'number' => $number,
            'current' => $number === $current,
            'past' => $number < $current,
            'specs' => [],
            'points' => 0,
            'proposed' => 0,
        ];
    }

    /**
     * What a spec takes of a sprint, in story points: what the backlog
     * says, and the size Economics gives a spec nobody sized.
     *
     * @param  array<string, mixed>  $spec
     */
    protected function size(array $spec): float
    {
        $points = max(0, (int) ($spec['points'] ?? 0));

        return (float) ($points > 0 ? $points : EffortEstimate::DEFAULT_SPEC_POINTS);
    }
}
