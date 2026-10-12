<?php

declare(strict_types=1);

namespace Larapilot\Services;

use DateTimeImmutable;
use DateTimeInterface;
use Larapilot\Support\EffortEstimate;

/**
 * How long the backlog takes: its story points, the hours a point stands
 * for, and the sprints one person needs — beside what the same work takes
 * with Larapilot, measured on the ledger when it holds the delivered specs,
 * modelled when it does not.
 *
 * Every number comes from one rule written here and said on the page: the
 * story points of the backlog, the hours per point Economics prices with,
 * the delivery day Plan and Economics count with, and the two-week sprint.
 */
class ForecastService
{
    /**
     * A sprint: two weeks of five working days.
     */
    public const SPRINT_WEEKS = 2;

    public const DAYS_PER_WEEK = 5;

    /**
     * How much faster Larapilot does the build work of a spec than a person
     * does, when the ledger has no measure yet. Writing and testing the code
     * is what the agent takes over; the human-in-the-loop time is not sped
     * up, it is the overhead below.
     *
     * @var array<string, float>
     */
    public const AI_SPEEDUP = ['optimistic' => 10.0, 'realistic' => 5.0];

    /**
     * Hours a person spends on each spec whatever its size, when Larapilot
     * builds it: reading the plan, reviewing the change, answering the
     * agent, approving.
     *
     * @var array<string, float>
     */
    public const AI_OVERHEAD_HOURS_PER_SPEC = ['optimistic' => 0.5, 'realistic' => 1.0];

    /**
     * Delivered specs with hours in the ledger it takes before the measured
     * rate is called more than a first reading.
     */
    public const MIN_MEASURED_SPECS = SpecActualsService::MIN_TIMED_SPECS;

    public function __construct(
        protected ConfigService $config,
        protected SpecService $specs,
        protected SpecActualsService $specActuals,
        protected UsageService $usage,
        protected EconomicsService $economics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $snapshot = $this->snapshot();

        return $snapshot + ['report_markdown' => $this->reportMarkdown($snapshot)];
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $statuses = [
            'done' => strtoupper($this->config->status('done')),
            'in_progress' => strtoupper($this->config->status('in_progress')),
            'review' => strtoupper($this->config->status('review')),
        ];

        $rows = $this->rows($statuses);
        $hoursPerPoint = $this->hoursPerPoint();
        $sprint = $this->sprint();
        $measured = $this->measured($rows);

        $points = ['total' => 0, 'done' => 0, 'wip' => 0, 'todo' => 0];
        $specs = ['total' => count($rows), 'done' => 0, 'wip' => 0, 'todo' => 0, 'unsized' => 0];
        $sizedSpecs = 0;
        $sizedOpenSpecs = 0;

        foreach ($rows as $row) {
            $points['total'] += $row['points'];
            $points[$row['state']] += $row['points'];
            $specs[$row['state']]++;

            if ($row['points'] === 0) {
                $specs['unsized']++;

                continue;
            }

            $sizedSpecs++;

            if ($row['state'] !== 'done') {
                $sizedOpenSpecs++;
            }
        }

        $points['remaining'] = $points['total'] - $points['done'];

        $human = [
            'hours_total' => round($points['total'] * $hoursPerPoint['value'], 1),
            'hours_remaining' => round($points['remaining'] * $hoursPerPoint['value'], 1),
        ];
        $human['sprints_total'] = $this->sprints($human['hours_total'], $sprint['hours']);
        $human['sprints_remaining'] = $this->sprints($human['hours_remaining'], $sprint['hours']);

        $ai = $measured !== null
            ? $this->measuredForecast($measured, $points, $human, $sprint['hours'])
            : $this->modelledForecast($points, $hoursPerPoint['value'], $sizedSpecs, $sizedOpenSpecs, $human, $sprint['hours']);

        return [
            'specs' => $specs,
            'points' => $points,
            'statuses' => $statuses,
            'hours_per_point' => $hoursPerPoint,
            'sprint' => $sprint,
            'human' => $human,
            'measured' => $measured,
            'ai' => $ai,
            'model' => [
                'speedup' => self::AI_SPEEDUP,
                'overhead_hours_per_spec' => self::AI_OVERHEAD_HOURS_PER_SPEC,
            ],
            'epics' => $this->epics($rows, $hoursPerPoint['value'], $sprint['hours'], $measured, $ai['basis']),
            'assumptions' => $this->assumptions($hoursPerPoint, $sprint, $measured, $specs['unsized']),
        ];
    }

    /**
     * Every spec of the backlog, in the few fields the forecast reads.
     *
     * @param  array{done: string, in_progress: string, review: string}  $statuses
     * @return list<array{code: string, title: string, points: int, state: 'done'|'wip'|'todo', epic_code: string|null, epic_title: string|null}>
     */
    protected function rows(array $statuses): array
    {
        $rows = [];

        foreach ($this->specs->allSpecs() as $spec) {
            $code = trim((string) ($spec['code'] ?? ''));

            if ($code === '') {
                continue;
            }

            $status = strtoupper(trim((string) ($spec['status'] ?? '')));
            $epic = is_array($spec['epic'] ?? null) ? $spec['epic'] : [];
            $epicCode = trim((string) ($epic['code'] ?? ''));

            $rows[] = [
                'code' => $code,
                'title' => (string) ($spec['title'] ?? $code),
                'points' => max(0, (int) ($spec['points'] ?? 0)),
                'state' => match (true) {
                    $status === $statuses['done'] => 'done',
                    in_array($status, [$statuses['in_progress'], $statuses['review']], true) => 'wip',
                    default => 'todo',
                },
                'epic_code' => $epicCode !== '' ? $epicCode : null,
                'epic_title' => $epicCode !== '' ? trim((string) ($epic['title'] ?? $epicCode)) : null,
            ];
        }

        return $rows;
    }

    /**
     * The hours a story point stands for when a person builds it: the rule
     * Economics prices the backlog with — the rate the plans imply once
     * enough of the backlog is planned, the effort setting until then.
     *
     * @return array{value: float, source: 'plans'|'settings', effort: string, planned_specs: int}
     */
    public function hoursPerPoint(): array
    {
        $estimates = $this->specActuals->estimates();
        $planned = 0;

        foreach ($estimates['specs'] as $estimate) {
            if ($estimate['from'] === 'plan') {
                $planned++;
            }
        }

        return [
            'value' => $estimates['hours_per_point'],
            'source' => $estimates['hours_per_point_source'] === 'plans' ? 'plans' : 'settings',
            'effort' => strtoupper((string) ($this->config->settings()['effort'] ?? 'STANDARD')),
            'planned_specs' => $planned,
        ];
    }

    /**
     * The hours one person has in a sprint. The day is the one of the
     * Economics profile when the project keeps one — the same billable day
     * the quote counts with — and the six-hour delivery day Plan and
     * Economics assume otherwise.
     *
     * @return array{hours_per_day: float, days_per_week: int, weeks: int, hours: float, source: 'economics'|'default'}
     */
    public function sprint(): array
    {
        $profile = $this->economics->read();
        $fromProfile = $this->economics->enabled() && ($profile['configured'] ?? false) === true;
        $hoursPerDay = max(1.0, (float) ($profile['hours_per_day'] ?? 6.0));

        return [
            'hours_per_day' => $hoursPerDay,
            'days_per_week' => self::DAYS_PER_WEEK,
            'weeks' => self::SPRINT_WEEKS,
            'hours' => round($hoursPerDay * self::DAYS_PER_WEEK * self::SPRINT_WEEKS, 1),
            'source' => $fromProfile ? 'economics' : 'default',
        ];
    }

    /**
     * What a story point took with Larapilot: the hours the ledger holds for
     * the delivered specs that carry points, over those points. A spec with
     * no entry says nothing and is left out; one with no points cannot be
     * read per point.
     *
     * @param  list<array{code: string, title: string, points: int, state: 'done'|'wip'|'todo', epic_code: string|null, epic_title: string|null}>  $rows
     * @return array{hours_per_point: float, specs: int, points: int, hours: float, thin: bool}|null
     */
    protected function measured(array $rows): ?array
    {
        $ledger = [];

        foreach ($this->usage->summary()['by_spec'] as $code => $bucket) {
            $ledger[strtoupper(trim((string) $code))] = (float) ($bucket['minutes'] ?? 0);
        }

        $minutes = 0.0;
        $points = 0;
        $specs = 0;

        foreach ($rows as $row) {
            $logged = $ledger[strtoupper($row['code'])] ?? 0.0;

            if ($row['state'] !== 'done' || $row['points'] === 0 || $logged < 1) {
                continue;
            }

            $minutes += $logged;
            $points += $row['points'];
            $specs++;
        }

        if ($specs === 0 || $points === 0) {
            return null;
        }

        return [
            'hours_per_point' => round($minutes / 60 / $points, 2),
            'specs' => $specs,
            'points' => $points,
            'hours' => round($minutes / 60, 1),
            'thin' => $specs < self::MIN_MEASURED_SPECS,
        ];
    }

    /**
     * @param  array{hours_per_point: float, specs: int, points: int, hours: float, thin: bool}  $measured
     * @param  array{total: int, done: int, wip: int, todo: int, remaining: int}  $points
     * @param  array{hours_total: float, hours_remaining: float, sprints_total: int, sprints_remaining: int}  $human
     * @return array<string, mixed>
     */
    protected function measuredForecast(array $measured, array $points, array $human, float $sprintHours): array
    {
        $figures = $this->figures(
            $points['total'] * $measured['hours_per_point'],
            $points['remaining'] * $measured['hours_per_point'],
            $human,
            $sprintHours
        );

        return [
            'basis' => 'measured',
            'optimistic' => $figures,
            'realistic' => $figures,
        ];
    }

    /**
     * The model, spec by spec: the build work of a person divided by the
     * speed-up, plus the fixed hours a person spends on every spec.
     *
     * @param  array{total: int, done: int, wip: int, todo: int, remaining: int}  $points
     * @param  array{hours_total: float, hours_remaining: float, sprints_total: int, sprints_remaining: int}  $human
     * @return array<string, mixed>
     */
    protected function modelledForecast(array $points, float $hoursPerPoint, int $sizedSpecs, int $sizedOpenSpecs, array $human, float $sprintHours): array
    {
        $forecast = ['basis' => 'model'];

        foreach (self::AI_SPEEDUP as $case => $speedup) {
            $overhead = self::AI_OVERHEAD_HOURS_PER_SPEC[$case];

            $forecast[$case] = $this->figures(
                $points['total'] * $hoursPerPoint / $speedup + $sizedSpecs * $overhead,
                $points['remaining'] * $hoursPerPoint / $speedup + $sizedOpenSpecs * $overhead,
                $human,
                $sprintHours
            );
        }

        return $forecast;
    }

    /**
     * @param  array{hours_total: float, hours_remaining: float, sprints_total: int, sprints_remaining: int}  $human
     * @return array{hours_total: float, hours_remaining: float, sprints_total: int, sprints_remaining: int, speedup: float|null}
     */
    protected function figures(float $hoursTotal, float $hoursRemaining, array $human, float $sprintHours): array
    {
        $hoursTotal = round($hoursTotal, 1);

        return [
            'hours_total' => $hoursTotal,
            'hours_remaining' => round($hoursRemaining, 1),
            'sprints_total' => $this->sprints($hoursTotal, $sprintHours),
            'sprints_remaining' => $this->sprints($hoursRemaining, $sprintHours),
            'speedup' => $hoursTotal > 0 && $human['hours_total'] > 0 ? round($human['hours_total'] / $hoursTotal, 1) : null,
        ];
    }

    /**
     * Sprints a number of hours fills: whole sprints, the last one started
     * counts.
     */
    public function sprints(float $hours, float $sprintHours): int
    {
        if ($hours <= 0 || $sprintHours <= 0) {
            return 0;
        }

        return (int) ceil(round($hours / $sprintHours, 6));
    }

    /**
     * One row for each epic, and one for the specs that have none. The AI
     * hours are the realistic case of the model, or the measured rate.
     *
     * @param  list<array{code: string, title: string, points: int, state: 'done'|'wip'|'todo', epic_code: string|null, epic_title: string|null}>  $rows
     * @param  array{hours_per_point: float, specs: int, points: int, hours: float, thin: bool}|null  $measured
     * @return list<array{code: string|null, title: string, specs: int, points: int, points_done: int, points_remaining: int, human_hours: float, ai_hours: float, sprints: int}>
     */
    protected function epics(array $rows, float $hoursPerPoint, float $sprintHours, ?array $measured, string $basis): array
    {
        $epics = [];

        foreach ($rows as $row) {
            $key = $row['epic_code'] ?? '';

            $epics[$key] ??= [
                'code' => $row['epic_code'],
                'title' => $row['epic_title'] ?? 'No epic',
                'specs' => 0,
                'points' => 0,
                'points_done' => 0,
                'points_remaining' => 0,
                'human_hours' => 0.0,
                'ai_hours' => 0.0,
                'sprints' => 0,
            ];

            $epics[$key]['specs']++;
            $epics[$key]['points'] += $row['points'];

            if ($row['state'] === 'done') {
                $epics[$key]['points_done'] += $row['points'];
            } else {
                $epics[$key]['points_remaining'] += $row['points'];
            }

            if ($row['points'] > 0) {
                $epics[$key]['ai_hours'] += $basis === 'measured' && $measured !== null
                    ? $row['points'] * $measured['hours_per_point']
                    : $row['points'] * $hoursPerPoint / self::AI_SPEEDUP['realistic'] + self::AI_OVERHEAD_HOURS_PER_SPEC['realistic'];
            }
        }

        foreach ($epics as &$epic) {
            $epic['human_hours'] = round($epic['points'] * $hoursPerPoint, 1);
            $epic['ai_hours'] = round($epic['ai_hours'], 1);
            $epic['sprints'] = $this->sprints($epic['human_hours'], $sprintHours);
        }

        unset($epic);

        // The epics in the order of their codes; the specs with none close the list.
        uksort($epics, static fn (string $a, string $b): int => match (true) {
            $a === '' => 1,
            $b === '' => -1,
            default => strnatcasecmp($a, $b),
        });

        return array_values($epics);
    }

    /**
     * What the page says the numbers rest on, in plain words.
     *
     * @param  array{value: float, source: 'plans'|'settings', effort: string, planned_specs: int}  $hoursPerPoint
     * @param  array{hours_per_day: float, days_per_week: int, weeks: int, hours: float, source: 'economics'|'default'}  $sprint
     * @param  array{hours_per_point: float, specs: int, points: int, hours: float, thin: bool}|null  $measured
     * @return list<string>
     */
    protected function assumptions(array $hoursPerPoint, array $sprint, ?array $measured, int $unsized): array
    {
        $lines = [];

        $lines[] = $hoursPerPoint['source'] === 'plans'
            ? sprintf('A story point is %s h of work for one person: the rate the %d planned specs imply, the same Economics prices the backlog with.', self::number($hoursPerPoint['value']), $hoursPerPoint['planned_specs'])
            : sprintf('A story point is assumed to be %s h of work for one person: the hours of the %s effort setting, the same Economics prices the backlog with. Plans calibrate it once two specs with five points between them are planned.', self::number($hoursPerPoint['value']), $hoursPerPoint['effort']);

        $lines[] = sprintf(
            'A sprint is two weeks of one person: %s h a day × %d days × %d weeks = %s h. The day is %s.',
            self::number($sprint['hours_per_day']),
            $sprint['days_per_week'],
            $sprint['weeks'],
            self::number($sprint['hours']),
            $sprint['source'] === 'economics'
                ? 'the billable day of your Economics profile'
                : 'the six-hour delivery day Plan and Economics count with; set the hours of a day in Economics (economics-set --hours-per-day) to change it'
        );

        $lines[] = 'Hours = story points × hours per point; sprints = hours ÷ the hours of a sprint, rounded up. Specs are counted one after another, with no parallel work.';

        if ($measured !== null) {
            $lines[] = sprintf(
                'With Larapilot a story point took %s h, measured on the %d delivered %s with hours in the ledger (%d SP, %s h logged with `--spec`).%s',
                self::number($measured['hours_per_point']),
                $measured['specs'],
                $measured['specs'] === 1 ? 'spec' : 'specs',
                $measured['points'],
                self::number($measured['hours']),
                $measured['thin'] ? sprintf(' Under %d specs it is a first reading, not a rate.', self::MIN_MEASURED_SPECS) : ''
            );
        } else {
            $lines[] = sprintf(
                'With Larapilot the build work of a spec is modelled %s× faster (optimistic) to %s× faster (realistic) than a person, plus %s h to %s h a person spends on every spec to read the plan, review the change, answer the agent, and approve. The range is a model until the ledger holds hours for a delivered spec.',
                self::number(self::AI_SPEEDUP['optimistic']),
                self::number(self::AI_SPEEDUP['realistic']),
                self::number(self::AI_OVERHEAD_HOURS_PER_SPEC['optimistic']),
                self::number(self::AI_OVERHEAD_HOURS_PER_SPEC['realistic'])
            );
        }

        if ($unsized > 0) {
            $lines[] = sprintf(
                '%d %s no story points and %s nothing to the hours. Economics sizes such a spec at %d points; this page counts what the backlog says.',
                $unsized,
                $unsized === 1 ? 'spec carries' : 'specs carry',
                $unsized === 1 ? 'adds' : 'add',
                EffortEstimate::DEFAULT_SPEC_POINTS
            );
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     */
    public function reportMarkdown(?array $snapshot = null): string
    {
        $snapshot ??= $this->snapshot();
        $specs = $snapshot['specs'];
        $points = $snapshot['points'];
        $human = $snapshot['human'];
        $ai = $snapshot['ai'];
        $hoursPerPoint = $snapshot['hours_per_point'];
        $sprint = $snapshot['sprint'];

        $lines = [
            '# Larapilot forecast',
            '',
            'Generated: '.(new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
            '',
        ];

        if ($specs['total'] === 0) {
            $lines[] = '_No specs in the backlog yet: nothing to forecast._';

            return implode("\n", $lines)."\n";
        }

        $lines[] = '## Backlog';
        $lines[] = '';
        $lines[] = sprintf('- **Specs:** %d (%d done · %d in progress or review · %d to do)', $specs['total'], $specs['done'], $specs['wip'], $specs['todo']);
        $lines[] = sprintf('- **Story points:** %d (%d done · %d in progress or review · %d to do · %d remaining)', $points['total'], $points['done'], $points['wip'], $points['todo'], $points['remaining']);
        $lines[] = '';
        $lines[] = '## One person';
        $lines[] = '';
        $lines[] = sprintf(
            '- **Hours per story point:** %s h (%s)',
            self::number($hoursPerPoint['value']),
            $hoursPerPoint['source'] === 'plans' ? 'from '.$hoursPerPoint['planned_specs'].' planned specs' : 'assumed, effort '.$hoursPerPoint['effort']
        );
        $lines[] = sprintf('- **Sprint:** %s h a day × %d days × %d weeks = %s h', self::number($sprint['hours_per_day']), $sprint['days_per_week'], $sprint['weeks'], self::number($sprint['hours']));
        $lines[] = sprintf('- **Hours:** %s h (%s h remaining)', self::number($human['hours_total']), self::number($human['hours_remaining']));
        $lines[] = sprintf('- **Sprints:** %d (%d remaining)', $human['sprints_total'], $human['sprints_remaining']);
        $lines[] = '';
        $lines[] = '## Human vs AI';
        $lines[] = '';
        $lines[] = $ai['basis'] === 'measured'
            ? '_The AI column is measured on the ledger of the delivered specs._'
            : '_The AI columns are a model: no delivered spec has hours in the ledger yet._';
        $lines[] = '';

        if ($ai['basis'] === 'measured') {
            $lines[] = '| | Human | AI (measured) |';
            $lines[] = '| --- | ---: | ---: |';
            $lines[] = sprintf('| Hours | %s h | %s h |', self::number($human['hours_total']), self::number($ai['realistic']['hours_total']));
            $lines[] = sprintf('| Hours remaining | %s h | %s h |', self::number($human['hours_remaining']), self::number($ai['realistic']['hours_remaining']));
            $lines[] = sprintf('| Sprints | %d | %d |', $human['sprints_total'], $ai['realistic']['sprints_total']);
            $lines[] = sprintf('| Sprints remaining | %d | %d |', $human['sprints_remaining'], $ai['realistic']['sprints_remaining']);
            $lines[] = sprintf('| Speed-up | 1× | %s |', self::times($ai['realistic']['speedup']));
        } else {
            $lines[] = '| | Human | AI optimistic | AI realistic |';
            $lines[] = '| --- | ---: | ---: | ---: |';
            $lines[] = sprintf('| Hours | %s h | %s h | %s h |', self::number($human['hours_total']), self::number($ai['optimistic']['hours_total']), self::number($ai['realistic']['hours_total']));
            $lines[] = sprintf('| Hours remaining | %s h | %s h | %s h |', self::number($human['hours_remaining']), self::number($ai['optimistic']['hours_remaining']), self::number($ai['realistic']['hours_remaining']));
            $lines[] = sprintf('| Sprints | %d | %d | %d |', $human['sprints_total'], $ai['optimistic']['sprints_total'], $ai['realistic']['sprints_total']);
            $lines[] = sprintf('| Sprints remaining | %d | %d | %d |', $human['sprints_remaining'], $ai['optimistic']['sprints_remaining'], $ai['realistic']['sprints_remaining']);
            $lines[] = sprintf('| Speed-up | 1× | %s | %s |', self::times($ai['optimistic']['speedup']), self::times($ai['realistic']['speedup']));
        }

        $lines[] = '';
        $lines[] = '## By epic';
        $lines[] = '';
        $lines[] = sprintf('| Epic | Specs | SP | Done | Human | AI %s | Sprints |', $ai['basis'] === 'measured' ? 'measured' : 'realistic');
        $lines[] = '| ---- | ----: | --: | ---: | ----: | --: | ------: |';

        foreach ($snapshot['epics'] as $epic) {
            $lines[] = sprintf(
                '| %s | %d | %d | %d | %s h | %s h | %d |',
                str_replace('|', '\\|', $epic['code'] !== null ? $epic['code'].' — '.$epic['title'] : $epic['title']),
                $epic['specs'],
                $epic['points'],
                $epic['points_done'],
                self::number($epic['human_hours']),
                self::number($epic['ai_hours']),
                $epic['sprints']
            );
        }

        $lines[] = '';
        $lines[] = '## Assumptions';
        $lines[] = '';

        foreach ($snapshot['assumptions'] as $assumption) {
            $lines[] = '- '.$assumption;
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * A number without the zeros it does not need: `40`, `7.6`, `0.25`.
     */
    public static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
    }

    /**
     * A speed-up as it is read: `3.6×`, `12×`; a dash when there is none.
     */
    public static function times(?float $ratio): string
    {
        return $ratio === null ? '—' : SpecActualsService::ratio($ratio);
    }
}
