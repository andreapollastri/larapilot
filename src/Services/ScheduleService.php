<?php

declare(strict_types=1);

namespace Larapilot\Services;

use DateTimeImmutable;
use Larapilot\Support\PlanDate;
use Larapilot\Support\SpecBlockers;

/**
 * The delivery forecast as a re-plan reads it and changes it: the queue of
 * the open specs, what the forecast could not read in its inputs, and the
 * batch of changes — priorities, blockers, estimates, dates — that puts the
 * project back in line with it.
 */
class ScheduleService
{
    /**
     * @var list<string>
     */
    public const PRIORITIES = ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'];

    /**
     * @var list<string>
     */
    public const STATUSES = ['on_track', 'at_risk', 'delayed', 'done'];

    /**
     * @var list<string>
     */
    public const PARTS = ['queue', 'epics', 'deadlines', 'releases', 'alerts', 'findings'];

    public function __construct(
        protected UsageService $usage,
        protected SpecService $specs,
        protected PlanService $plans,
        protected ReleaseService $releases,
    ) {}

    /**
     * What is on disk, in the shape the forecast takes.
     *
     * @return array{specs: list<array<string, mixed>>, plans: array<string, array<string, mixed>|null>, schedule: array<string, mixed>}
     */
    public function inputs(): array
    {
        $specs = [];
        $plans = [];

        foreach ($this->specs->allSpecs() as $spec) {
            $code = (string) ($spec['code'] ?? '');

            if ($code !== '') {
                $specs[] = $spec;
                $plans[$code] = $this->plans->read($code);
            }
        }

        return ['specs' => $specs, 'plans' => $plans, 'schedule' => $this->usage->schedule()];
    }

    /**
     * The forecast, short enough to reason on: the open specs in the order
     * they are delivered, every date measured against it, and the findings.
     *
     * @param  array{specs: list<array<string, mixed>>, plans: array<string, array<string, mixed>|null>, schedule: array<string, mixed>}|null  $inputs  What to forecast in place of what is on disk.
     * @return array<string, mixed>
     */
    public function show(?array $inputs = null): array
    {
        $inputs ??= $this->inputs();
        $gantt = $this->usage->gantt($inputs);
        $criticality = $this->usage->criticality($gantt, $inputs);
        $forecastEnd = $gantt['forecast_end'];
        $today = $gantt['today'];

        $specs = [];

        foreach ($inputs['specs'] as $spec) {
            $specs[(string) $spec['code']] = $spec;
        }

        // What the chart drew for each spec: its window, its open hours, its blockers.
        $drawn = [];

        foreach ($gantt['bars'] as $bar) {
            if ($bar['type'] === 'epic') {
                continue;
            }

            $task = $bar['type'] === 'task';
            $code = $task ? explode('·', (string) $bar['id'], 2)[0] : (string) $bar['id'];
            $drawn[$code] ??= ['start' => $bar['start'], 'end' => $bar['end'], 'hours' => 0.0, 'planned' => $task, 'blocked_by' => [], 'tasks' => []];
            $drawn[$code]['start'] = min($drawn[$code]['start'], $bar['start']);
            $drawn[$code]['end'] = max($drawn[$code]['end'], $bar['end']);
            $drawn[$code]['blocked_by'] = $task ? ($bar['spec_depends_on'] ?? []) : $bar['depends_on'];

            if (! $task) {
                $drawn[$code]['hours'] = (float) ($bar['remaining_hours'] ?? 0);
            } elseif ($bar['status'] !== 'DONE') {
                $drawn[$code]['hours'] += (float) $bar['estimate_hours'];
                $drawn[$code]['tasks'][] = $bar;
            }
        }

        $queue = [];

        foreach ($gantt['queue'] as $index => $code) {
            $spec = $specs[$code] ?? [];
            $row = $drawn[$code] ?? null;

            if ($row === null) {
                continue;
            }

            $queue[] = [
                'n' => $index + 1,
                'code' => $code,
                'title' => (string) ($spec['title'] ?? ''),
                'epic' => is_array($spec['epic'] ?? null) ? ($spec['epic']['code'] ?? null) : null,
                'status' => strtoupper((string) ($spec['status'] ?? 'TODO')),
                'priority' => strtoupper((string) ($spec['priority'] ?? '')),
                'points' => (int) ($spec['points'] ?? 0),
                'hours' => round($row['hours'], 1),
                'estimate' => $row['planned'] ? 'plan' : 'points',
                'start' => $row['start'],
                'end' => $row['end'],
                'blocked_by' => $row['blocked_by'],
            ];
        }

        $epics = [];

        foreach ($gantt['epics'] as $epic) {
            $epics[] = [
                'code' => $epic['code'],
                'title' => $epic['title'],
                'deadline' => $epic['deadline'],
                'forecast_end' => $epic['forecast_end'],
                'slip_days' => $epic['deadline'] !== null && ! $epic['done']
                    ? $this->daysPast($epic['deadline'], $epic['forecast_end'])
                    : 0,
                'points' => $epic['points'],
                'done_points' => $epic['done_points'],
                'specs' => count($epic['spec_codes']),
                'done' => $epic['done'],
            ];
        }

        $deadlines = [];

        foreach ($inputs['schedule']['deadlines'] ?? [] as $deadline) {
            $date = PlanDate::day($deadline['date'] ?? null) ?? '';
            $status = $this->usage->deadlineStatus($deadline);
            $release = trim((string) ($deadline['release'] ?? '')) ?: null;
            // A milestone that names a release waits for that release, not for the whole backlog.
            $releaseEnd = $release !== null ? $this->usage->releaseForecast($release, $gantt) : null;
            $target = $releaseEnd ?? $forecastEnd;

            $deadlines[] = [
                'id' => (string) ($deadline['id'] ?? ''),
                'label' => (string) ($deadline['label'] ?? 'Deadline'),
                'date' => $date,
                'status' => $status,
                'release' => $release,
                'release_empty' => $release !== null && $releaseEnd === null,
                'forecast_end' => $target,
                'slip_days' => $status !== 'done' && $target !== null && $this->isDate($date)
                    ? $this->daysPast($date, $target)
                    : 0,
                'overdue' => $status !== 'done' && $date !== '' && $date < $today,
            ];
        }

        $releases = [];

        foreach ($this->releases->read()['releases'] as $release) {
            if (($release['status'] ?? '') === 'shipped') {
                continue;
            }

            $codes = array_map('strval', is_array($release['specs'] ?? null) ? $release['specs'] : []);
            $ends = array_values(array_filter(array_map(
                static fn (string $code): ?string => $drawn[$code]['end'] ?? null,
                $codes
            )));

            $releases[] = [
                'version' => (string) ($release['version'] ?? ''),
                'title' => (string) ($release['title'] ?? ''),
                'status' => (string) ($release['status'] ?? ''),
                'specs' => count($codes),
                'forecast_end' => $ends !== [] ? max($ends) : null,
            ];
        }

        return [
            'today' => $today,
            'forecast_end' => $forecastEnd,
            'remaining' => [
                'specs' => count($queue),
                'points' => $criticality['remaining_points'],
                'hours' => $criticality['remaining_hours'],
                'work_days' => $criticality['forecast_work_days'],
            ],
            'assumptions' => $gantt['assumptions'] + [
                'working_days' => 'Monday to Friday',
                'order' => 'in progress first, then priority and code, never before a blocker, and a blocker as urgent as what it blocks; one spec at a time',
            ],
            'queue' => $queue,
            'epics' => $epics,
            'deadlines' => $deadlines,
            'releases' => $releases,
            'alerts' => $criticality['alerts'],
            'findings' => $this->findings($inputs, $specs, $drawn, $epics, $deadlines),
        ];
    }

    /**
     * Forecast a re-plan and, unless it is a dry run, write it: the specs to
     * the backlog, the tasks to their plans, the milestones to the schedule.
     * Nothing is written while one change of the batch is wrong.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function apply(array $payload, bool $dryRun = false): array
    {
        $inputs = $this->inputs();
        $change = $this->change($inputs, $payload);

        if ($change['findings'] !== []) {
            return ['ok' => false, 'findings' => $change['findings']];
        }

        $before = $this->show($inputs);
        $after = $this->show($change['inputs']);

        if (! $dryRun) {
            if ($change['specs'] !== []) {
                $this->specs->add(array_values(array_filter(
                    $change['inputs']['specs'],
                    static fn (array $spec): bool => isset($change['specs'][(string) $spec['code']])
                )));
            }

            foreach (array_keys($change['plans']) as $code) {
                $this->plans->replaceTasks((string) $code, $change['inputs']['plans'][$code]['tasks']);
            }

            if ($change['deadlines'] > 0) {
                $this->usage->replaceDeadlines($change['inputs']['schedule']['deadlines']);
            }

            if ($change['note'] !== null) {
                $this->usage->addScheduleNote($change['note']);
            }
        }

        return [
            'ok' => true,
            'dry_run' => $dryRun,
            'changed' => [
                'specs' => count($change['specs']),
                'tasks' => $change['tasks'],
                'epics' => $change['epics'],
                'deadlines' => $change['deadlines'],
                'note' => $change['note'] !== null,
            ],
            'before' => [
                'forecast_end' => $before['forecast_end'],
                'alerts' => count($before['alerts']),
                'findings' => count($before['findings']),
                'epics' => array_column($before['epics'], 'forecast_end', 'code'),
            ],
            'after' => $after,
        ];
    }

    /**
     * What a project planned on an earlier version carries that the forecast
     * cannot read, put right where it takes no decision: a milestone with no
     * id or with a date that is not a plain day, a milestone named after a
     * release and not tied to it, an epic whose deadline only some of its
     * specs carry. Whatever needs a choice is left to the findings.
     *
     * @return array{dry_run: bool, fixes: list<array{code: string, path: string, message: string}>}
     */
    public function repair(bool $dryRun = false): array
    {
        $inputs = $this->inputs();
        $fixes = [];
        $fix = static function (string $code, string $path, string $message) use (&$fixes): void {
            $fixes[] = compact('code', 'path', 'message');
        };

        $deadlines = array_values(is_array($inputs['schedule']['deadlines'] ?? null) ? $inputs['schedule']['deadlines'] : []);

        foreach ($deadlines as $index => $deadline) {
            $label = (string) ($deadline['label'] ?? 'Deadline');

            if (trim((string) ($deadline['id'] ?? '')) === '') {
                $deadlines[$index]['id'] = bin2hex(random_bytes(6));
                $fix('SCHEDULE_FIX_DEADLINE_ID', $label, "{$label}: given an id, so a re-plan can move it.");
            }

            $day = $this->day($deadline['date'] ?? null);

            if ($day !== null && $day !== ($deadline['date'] ?? null)) {
                $deadlines[$index]['date'] = $day;
                $fix('SCHEDULE_FIX_DEADLINE_DATE', $label, "{$label}: date written as {$day}.");
            }

            if (trim((string) ($deadline['release'] ?? '')) !== '' || ($deadline['status'] ?? '') === 'done') {
                continue;
            }

            // A milestone that carries the version of one release in its label is that release's.
            preg_match_all('/(?<![\d.])v?(\d+\.\d+\.\d+)(?![\d.])/', $label, $versions);
            $named = [];

            foreach (array_unique($versions[1]) as $version) {
                $release = $this->releases->find($version);

                if ($release !== null) {
                    $named[] = (string) $release['version'];
                }
            }

            if (count($named) === 1) {
                $deadlines[$index]['release'] = $named[0];
                $fix('SCHEDULE_FIX_DEADLINE_RELEASE', $label, "{$label}: tied to release {$named[0]}, and measured against its last spec.");
            }
        }

        $moved = $fixes !== [];

        // The deadline of an epic is written on each of its specs: one value, on all of them.
        $epics = [];

        foreach ($inputs['specs'] as $index => $spec) {
            if (is_array($spec['epic'] ?? null) && trim((string) ($spec['epic']['code'] ?? '')) !== '') {
                $epics[strtoupper((string) $spec['epic']['code'])][$index] = $this->day($spec['epic']['deadline'] ?? null);
            }
        }

        $changed = [];

        foreach ($epics as $epic => $days) {
            $agreed = array_values(array_unique(array_filter($days)));

            if (count($agreed) !== 1) {
                continue;
            }

            $carried = 0;

            foreach (array_keys($days) as $index) {
                if (($inputs['specs'][$index]['epic']['deadline'] ?? null) !== $agreed[0]) {
                    $inputs['specs'][$index]['epic']['deadline'] = $agreed[0];
                    $changed[] = $inputs['specs'][$index];
                    $carried++;
                }
            }

            if ($carried > 0) {
                $fix('SCHEDULE_FIX_EPIC_DEADLINE', $epic, "{$epic}: deadline {$agreed[0]} written on {$carried} of its ".count($days).' specs.');
            }
        }

        if (! $dryRun) {
            if ($moved) {
                $this->usage->replaceDeadlines($deadlines);
            }

            if ($changed !== []) {
                $this->specs->add($changed);
            }
        }

        return ['dry_run' => $dryRun, 'fixes' => $fixes];
    }

    /**
     * A date as the forecast compares it: a plain day. YAML written by hand
     * turns an unquoted date into a timestamp.
     */
    protected function day(mixed $date): ?string
    {
        return PlanDate::day($date);
    }

    /**
     * The inputs as the re-plan leaves them, and what is wrong with it.
     *
     * @param  array{specs: list<array<string, mixed>>, plans: array<string, array<string, mixed>|null>, schedule: array<string, mixed>}  $inputs
     * @param  array<string, mixed>  $payload
     * @return array{inputs: array{specs: list<array<string, mixed>>, plans: array<string, array<string, mixed>|null>, schedule: array<string, mixed>}, specs: array<string, true>, plans: array<string, true>, tasks: int, epics: int, deadlines: int, note: array{note: string, status: string}|null, findings: list<array<string, string>>}
     */
    protected function change(array $inputs, array $payload): array
    {
        $findings = [];
        $changedSpecs = [];
        $changedPlans = [];
        $tasks = 0;
        $epics = 0;
        $deadlines = 0;
        $cyclesBefore = $this->cycles($this->blockers($inputs['specs']));

        $known = [];

        foreach ($inputs['specs'] as $index => $spec) {
            $known[strtoupper((string) $spec['code'])] = $index;
        }

        foreach ($this->rows($payload, 'specs', $findings) as $at => $row) {
            $path = "specs[{$at}]";
            $index = $known[strtoupper(trim((string) ($row['code'] ?? '')))] ?? null;

            if ($index === null) {
                $findings[] = $this->finding('SCHEDULE_UNKNOWN_SPEC', 'error', $path.'.code', 'No spec has this code: '.(string) ($row['code'] ?? '(none)').'.', 'Use a code of `schedule-show` → `queue`.');

                continue;
            }

            $spec = $inputs['specs'][$index];
            $code = (string) $spec['code'];
            $body = (string) ($spec['body'] ?? '');
            $touched = false;
            $refused = count($findings);

            if (array_key_exists('priority', $row)) {
                $priority = strtoupper(trim((string) $row['priority']));

                if (! in_array($priority, self::PRIORITIES, true)) {
                    $findings[] = $this->finding('SCHEDULE_INVALID_PRIORITY', 'error', $path.'.priority', "Not a priority: {$priority}.", 'One of '.implode(', ', self::PRIORITIES).'.');
                } else {
                    $spec['priority'] = $priority;
                    $body = (string) preg_replace('/(\*\*Priority:?\*\*:?\s*)[A-Za-z]+/', '${1}'.$priority, $body, 1);
                    $touched = true;
                }
            }

            if (array_key_exists('points', $row)) {
                if (! is_numeric($row['points']) || (int) $row['points'] < 1) {
                    $findings[] = $this->finding('SCHEDULE_INVALID_POINTS', 'error', $path.'.points', 'Points are a whole number, 1 or more.', 'Size the story before it is scheduled.');
                } else {
                    $spec['points'] = (int) $row['points'];
                    $body = (string) preg_replace('/(\*\*Points:?\*\*:?\s*)\d+/', '${1}'.$spec['points'], $body, 1);
                    $touched = true;
                }
            }

            if (array_key_exists('blocked_by', $row)) {
                $blockers = [];

                foreach ($this->names($row['blocked_by']) as $blocker) {
                    $other = $known[strtoupper(trim((string) $blocker))] ?? null;

                    if ($other === null || $other === $index) {
                        $findings[] = $this->finding('SCHEDULE_UNKNOWN_BLOCKER', 'error', $path.'.blocked_by', "{$code} cannot wait for ".(string) $blocker.': '.($other === null ? 'no spec has this code.' : 'it is the spec itself.'), 'Name specs of the backlog, or `[]` for none.');

                        continue;
                    }

                    $blockers[] = (string) $inputs['specs'][$other]['code'];
                }

                $body = SpecBlockers::write($body, array_values(array_unique($blockers)));
                $touched = true;
            }

            if (count($findings) > $refused) {
                continue;
            }

            if (! $touched) {
                $findings[] = $this->finding('SCHEDULE_NO_CHANGE', 'error', $path, "{$code} is named with nothing to change.", 'Set `priority`, `points`, or `blocked_by`.');

                continue;
            }

            $spec['body'] = $body;
            $inputs['specs'][$index] = $spec;
            $changedSpecs[$code] = true;
        }

        foreach ($this->rows($payload, 'epics', $findings) as $at => $row) {
            $path = "epics[{$at}]";
            $epic = strtoupper(trim((string) ($row['code'] ?? '')));
            $deadline = $row['deadline'] ?? null;
            // A YAML payload hands an unquoted date over as a timestamp: it is a date, not "none".
            $deadline = is_int($deadline) ? $this->day($deadline) : $deadline;
            $given = $deadline !== null && $deadline !== '';
            $deadline = is_string($deadline) && trim($deadline) !== '' ? trim($deadline) : null;

            if (! array_key_exists('deadline', $row) || ($given && ($deadline === null || ! $this->isDate($deadline)))) {
                $findings[] = $this->finding('SCHEDULE_INVALID_DATE', 'error', $path.'.deadline', 'An epic takes a `deadline`: a date as YYYY-MM-DD, or null for none.', 'Read the forecast of the epic in `schedule-show` → `epics`.');

                continue;
            }

            $found = false;

            foreach ($inputs['specs'] as $index => $spec) {
                if (! is_array($spec['epic'] ?? null) || strtoupper((string) ($spec['epic']['code'] ?? '')) !== $epic) {
                    continue;
                }

                $found = true;

                if ($deadline === null) {
                    unset($spec['epic']['deadline']);
                } else {
                    $spec['epic']['deadline'] = $deadline;
                }

                $inputs['specs'][$index] = $spec;
                $changedSpecs[(string) $spec['code']] = true;
            }

            if (! $found) {
                $findings[] = $this->finding('SCHEDULE_UNKNOWN_EPIC', 'error', $path.'.code', 'No spec belongs to this epic: '.($epic !== '' ? $epic : '(none)').'.', 'Use a code of `schedule-show` → `epics`.');

                continue;
            }

            $epics++;
        }

        foreach ($this->rows($payload, 'tasks', $findings) as $at => $row) {
            $path = "tasks[{$at}]";
            $index = $known[strtoupper(trim((string) ($row['spec'] ?? '')))] ?? null;
            $code = $index !== null ? (string) $inputs['specs'][$index]['code'] : '';
            $plan = $inputs['plans'][$code] ?? null;
            $planTasks = is_array($plan['tasks'] ?? null) ? $plan['tasks'] : [];
            $ids = [];

            foreach ($planTasks as $position => $task) {
                if (is_array($task) && ! empty($task['id'])) {
                    $ids[strtoupper((string) $task['id'])] = $position;
                }
            }

            $position = $ids[strtoupper(trim((string) ($row['id'] ?? '')))] ?? null;

            if ($position === null) {
                $findings[] = $this->finding('SCHEDULE_UNKNOWN_TASK', 'error', $path, 'No such task: '.(string) ($row['spec'] ?? '(no spec)').' / '.(string) ($row['id'] ?? '(no id)').'.', 'A task is named by `spec` and `id`, and its spec has a plan.');

                continue;
            }

            $task = $planTasks[$position];
            $touched = false;
            $refused = count($findings);

            if (array_key_exists('estimate_hours', $row)) {
                if (! is_numeric($row['estimate_hours']) || (float) $row['estimate_hours'] <= 0) {
                    $findings[] = $this->finding('SCHEDULE_INVALID_HOURS', 'error', $path.'.estimate_hours', 'An estimate is a number of hours above zero.', 'Estimate the task, do not zero it.');
                } else {
                    $task['estimate_hours'] = round((float) $row['estimate_hours'], 1);
                    $touched = true;
                }
            }

            if (array_key_exists('assignee', $row)) {
                $assignee = trim((string) ($row['assignee'] ?? ''));

                if ($assignee === '') {
                    unset($task['assignee']);
                } else {
                    $task['assignee'] = $assignee;
                }

                $touched = true;
            }

            if (array_key_exists('dependencies', $row)) {
                $dependencies = [];

                foreach ($this->names($row['dependencies']) as $dependency) {
                    $other = $ids[strtoupper(trim((string) $dependency))] ?? null;

                    if ($other === null || $other === $position) {
                        $findings[] = $this->finding('SCHEDULE_UNKNOWN_DEPENDENCY', 'error', $path.'.dependencies', (string) $task['id'].' cannot wait for '.(string) $dependency.': not another task of this plan.', 'Name task ids of the same plan, or `[]` for none.');

                        continue;
                    }

                    $dependencies[] = (string) $planTasks[$other]['id'];
                }

                $task['dependencies'] = array_values(array_unique($dependencies));
                $touched = true;
            }

            if (count($findings) > $refused) {
                continue;
            }

            if (! $touched) {
                $findings[] = $this->finding('SCHEDULE_NO_CHANGE', 'error', $path, (string) $task['id'].' is named with nothing to change.', 'Set `estimate_hours`, `assignee`, or `dependencies`.');

                continue;
            }

            $planTasks[$position] = $task;
            $plan['tasks'] = $planTasks;
            $inputs['plans'][$code] = $plan;
            $changedPlans[$code] = true;
            $tasks++;
        }

        $milestones = array_values(is_array($inputs['schedule']['deadlines'] ?? null) ? $inputs['schedule']['deadlines'] : []);

        foreach ($this->rows($payload, 'deadlines', $findings) as $at => $row) {
            $path = "deadlines[{$at}]";
            $id = trim((string) ($row['id'] ?? ''));
            $position = null;

            if ($id === '' && ($row['remove'] ?? false) === true) {
                $findings[] = $this->finding('SCHEDULE_UNKNOWN_DEADLINE', 'error', $path.'.id', 'A removal names the milestone by its `id`.', 'Use an id of `schedule-show` → `deadlines`.');

                continue;
            }

            if ($id !== '') {
                foreach ($milestones as $index => $milestone) {
                    if ((string) ($milestone['id'] ?? '') === $id) {
                        $position = $index;
                    }
                }

                if ($position === null) {
                    $findings[] = $this->finding('SCHEDULE_UNKNOWN_DEADLINE', 'error', $path.'.id', "No milestone has the id {$id}.", 'Use an id of `schedule-show` → `deadlines`, or leave `id` out for a new one.');

                    continue;
                }

                if (($row['remove'] ?? false) === true) {
                    array_splice($milestones, $position, 1);
                    $deadlines++;

                    continue;
                }
            }

            $milestone = $position !== null
                ? $milestones[$position]
                : ['id' => bin2hex(random_bytes(6)), 'label' => 'Deadline', 'date' => '', 'status' => 'on_track', 'note' => null];

            if (array_key_exists('date', $row)) {
                $milestone['date'] = is_int($row['date']) ? (string) $this->day($row['date']) : trim((string) $row['date']);
            }

            if (! $this->isDate((string) $milestone['date'])) {
                $findings[] = $this->finding('SCHEDULE_INVALID_DATE', 'error', $path.'.date', 'A milestone takes a `date` as YYYY-MM-DD.', 'A new milestone needs its date.');

                continue;
            }

            if (array_key_exists('status', $row)) {
                $status = is_string($row['status']) ? trim($row['status']) : '';

                if (! in_array($status, self::STATUSES, true)) {
                    $findings[] = $this->finding('SCHEDULE_INVALID_STATUS', 'error', $path.'.status', "Not a status of a milestone: {$status}.", 'One of '.implode(', ', self::STATUSES).'.');

                    continue;
                }

                $milestone['status'] = $status;
            }

            if (trim((string) ($row['label'] ?? '')) !== '') {
                $milestone['label'] = trim((string) $row['label']);
            }

            if (array_key_exists('release', $row)) {
                unset($milestone['release']);

                if (trim((string) ($row['release'] ?? '')) !== '') {
                    try {
                        $milestone['release'] = $this->usage->releaseVersion(trim((string) $row['release']));
                    } catch (\InvalidArgumentException $e) {
                        $findings[] = $this->finding('SCHEDULE_UNKNOWN_RELEASE', 'error', $path.'.release', $e->getMessage(), 'Use a version of `schedule-show` → `releases`, or null for the whole backlog.');

                        continue;
                    }
                }
            }

            if (array_key_exists('note', $row)) {
                $milestone['note'] = trim((string) ($row['note'] ?? '')) ?: null;
            }

            if ($position !== null) {
                $milestones[$position] = $milestone;
            } else {
                $milestones[] = $milestone;
            }

            $deadlines++;
        }

        $inputs['schedule']['deadlines'] = $milestones;

        $text = trim((string) ($payload['note'] ?? ''));
        $note = $text !== '' ? ['note' => $text, 'status' => (string) ($payload['status'] ?? 'on_track')] : null;

        if ($findings === [] && $changedSpecs === [] && $tasks === 0 && $deadlines === 0 && $note === null) {
            $findings[] = $this->finding('SCHEDULE_EMPTY', 'error', 'payload', 'The re-plan changes nothing.', 'Give at least one of `specs`, `tasks`, `epics`, `deadlines`, `note`.');
        }

        foreach ($this->cycles($this->blockers($inputs['specs'])) as $cycle) {
            if (! in_array($cycle, $cyclesBefore, true)) {
                $findings[] = $this->finding('SCHEDULE_BLOCKER_CYCLE', 'error', 'specs', 'These specs would wait for each other: '.implode(' → ', $cycle).'.', 'Drop one blocker of the ring.');
            }
        }

        return [
            'inputs' => $inputs,
            'specs' => $changedSpecs,
            'plans' => $changedPlans,
            'tasks' => $tasks,
            'epics' => $epics,
            'deadlines' => $deadlines,
            'note' => $note,
            'findings' => $findings,
        ];
    }

    /**
     * What the forecast could not read, or read as a default, in its inputs.
     *
     * @param  array{specs: list<array<string, mixed>>, plans: array<string, array<string, mixed>|null>, schedule: array<string, mixed>}  $inputs
     * @param  array<string, array<string, mixed>>  $specs  by code
     * @param  array<string, array<string, mixed>>  $drawn  by code
     * @param  list<array<string, mixed>>  $epics
     * @param  list<array<string, mixed>>  $deadlines
     * @return list<array<string, string>>
     */
    protected function findings(array $inputs, array $specs, array $drawn, array $epics, array $deadlines): array
    {
        $findings = [];
        $rank = array_flip(self::PRIORITIES);
        $open = [];

        foreach ($specs as $code => $spec) {
            if (strtoupper((string) ($spec['status'] ?? 'TODO')) !== 'DONE') {
                $open[(string) $code] = $rank[strtoupper((string) ($spec['priority'] ?? ''))] ?? -1;
            }
        }

        foreach ($open as $code => $priority) {
            $code = (string) $code;
            $spec = $specs[$code];
            $named = SpecBlockers::read((string) ($spec['body'] ?? ''));

            if ($named === null) {
                $findings[] = $this->finding('SCHEDULE_NO_BLOCKED_BY', 'info', $code, "{$code} has no `**Blocked by:**` line: the forecast takes it as waiting for nothing.", 'Name its blockers, or `[]` for none, under `specs[].blocked_by`.');
            }

            foreach ($named ?? [] as $blocker) {
                $match = null;

                foreach (array_keys($specs) as $candidate) {
                    if (strtoupper((string) $candidate) === $blocker) {
                        $match = (string) $candidate;
                    }
                }

                if ($match === null || $match === $code) {
                    $findings[] = $this->finding('SCHEDULE_UNKNOWN_BLOCKER', 'warning', $code, "{$code} is blocked by {$blocker}, which ".($match === null ? 'is not in the backlog' : 'is the spec itself').': the forecast ignores it.', 'Rewrite `specs[].blocked_by` with specs of the backlog.');

                }
            }

            if ($priority < 0) {
                $findings[] = $this->finding('SCHEDULE_NO_PRIORITY', 'info', $code, "{$code} has no priority the forecast knows: it is queued as MEDIUM.", 'Set `specs[].priority`.');
            }

            $planned = ($drawn[$code]['planned'] ?? false) === true;

            if (! $planned && (int) ($spec['points'] ?? 0) < 1) {
                $findings[] = $this->finding('SCHEDULE_NO_POINTS', 'warning', $code, "{$code} has no plan and no points: it counts as one point.", 'Set `specs[].points`, or plan the spec.');
            }

            if (! $planned) {
                continue;
            }

            $tasks = array_values(array_filter(
                is_array($inputs['plans'][$code]['tasks'] ?? null) ? $inputs['plans'][$code]['tasks'] : [],
                static fn (mixed $task): bool => is_array($task) && ! empty($task['id'])
            ));
            $ids = array_map(static fn (array $task): string => (string) $task['id'], $tasks);
            $pending = array_values(array_filter(
                $tasks,
                static fn (array $task): bool => strtoupper((string) ($task['status'] ?? '')) !== 'DONE'
            ));
            $unestimated = array_values(array_filter(
                $pending,
                static fn (array $task): bool => ! is_numeric($task['estimate_hours'] ?? null)
            ));

            if ($unestimated !== []) {
                $findings[] = $this->finding('SCHEDULE_TASK_NO_ESTIMATE', 'warning', $code, count($unestimated).' of '.count($pending)." open tasks of {$code} have no `estimate_hours` (".implode(', ', array_column($unestimated, 'id')).'): each counts an equal share of the story points.', 'Set `tasks[].estimate_hours`.');
            }

            foreach ($pending as $task) {
                $missing = array_diff(
                    array_filter(is_array($task['dependencies'] ?? null) ? $task['dependencies'] : [], 'is_string'),
                    $ids
                );

                if ($missing !== []) {
                    $findings[] = $this->finding('SCHEDULE_UNKNOWN_DEPENDENCY', 'warning', $code.' / '.(string) $task['id'], (string) $task['id'].' waits for '.implode(', ', $missing).", which the plan of {$code} does not have: the forecast ignores it.", 'Rewrite `tasks[].dependencies`.');
                }
            }

            $parallel = array_values(array_filter(
                $drawn[$code]['tasks'],
                static fn (array $bar): bool => $bar['parallel'] === true
            ));
            $people = array_unique(array_map(
                static fn (array $bar): string => strtolower((string) $bar['assignee']),
                $parallel
            ));

            if (count($parallel) > 1 && count($people) === 1) {
                $findings[] = $this->finding('SCHEDULE_PARALLEL_UNUSED', 'info', $code, implode(', ', array_column($parallel, 'task_id'))." of {$code} could run at the same time and have one assignee, or none: they run one after another.", 'Give them different `tasks[].assignee` when more than one person works on the spec.');
            }
        }

        foreach ($this->cycles($this->blockers($inputs['specs'])) as $cycle) {
            $findings[] = $this->finding('SCHEDULE_BLOCKER_CYCLE', 'error', $cycle[0], 'These specs wait for each other: '.implode(' → ', $cycle).'. The forecast breaks the ring by priority.', 'Drop one blocker of the ring.');
        }

        $written = [];

        foreach ($specs as $spec) {
            if (is_array($spec['epic'] ?? null) && ($day = $this->day($spec['epic']['deadline'] ?? null)) !== null) {
                $written[strtoupper((string) ($spec['epic']['code'] ?? ''))][$day] = true;
            }
        }

        foreach ($written as $epic => $days) {
            if (count($days) > 1) {
                $findings[] = $this->finding('SCHEDULE_EPIC_DEADLINES_DIFFER', 'warning', (string) $epic, "The specs of {$epic} carry more than one deadline: ".implode(', ', array_keys($days)).'.', 'Set the one that holds under `epics[].deadline`: it is written on every spec of the epic.');
            }
        }

        $dated = false;

        foreach ($epics as $epic) {
            $dated = $dated || $epic['deadline'] !== null;

            if ($epic['deadline'] === null && ! $epic['done']) {
                $findings[] = $this->finding('SCHEDULE_EPIC_NO_DEADLINE', 'info', (string) $epic['code'], $epic['code'].' has no deadline; its forecast is '.$epic['forecast_end'].'.', 'Set `epics[].deadline` once the date is agreed.');
            }
        }

        foreach ($deadlines as $deadline) {
            $dated = true;

            if ($deadline['date'] === '') {
                $findings[] = $this->finding('SCHEDULE_INVALID_DATE', 'error', (string) $deadline['label'], $deadline['label'].' has a date the forecast cannot read: it is measured against nothing.', 'Set its `date` as YYYY-MM-DD under `deadlines[]`.');

                continue;
            }

            if ($deadline['release'] !== null && $deadline['release_empty'] && $deadline['status'] !== 'done') {
                $findings[] = $this->finding('SCHEDULE_RELEASE_EMPTY', 'warning', (string) $deadline['label'], $deadline['label'].' names release '.$deadline['release'].', which has no spec in the chart: it is measured against the whole backlog.', 'Add specs to the release (`release-add`), or point the milestone at the release it is for.');
            }

            if ($deadline['status'] === 'on_track' && ($deadline['slip_days'] > 0 || $deadline['overdue'])) {
                $findings[] = $this->finding('SCHEDULE_STALE_STATUS', 'warning', (string) $deadline['label'], $deadline['label'].' ('.$deadline['date'].') says on_track, and '.($deadline['overdue'] ? 'the date is past.' : ($deadline['release'] !== null ? 'release '.$deadline['release'] : 'the backlog').' is forecast for '.$deadline['forecast_end'].', '.$deadline['slip_days'].' days after it.'), 'Move its `date`, name the `release` it is for, or set its `status` to at_risk or delayed, under `deadlines[]`.');
            }
        }

        if (! $dated && $open !== []) {
            $findings[] = $this->finding('SCHEDULE_NO_DATES', 'info', 'schedule', 'No milestone and no epic deadline: the forecast is measured against nothing.', 'Agree the dates, then add them under `deadlines[]` and `epics[]`.');
        }

        return $findings;
    }

    /**
     * Which open specs each open spec waits for.
     *
     * @param  list<array<string, mixed>>  $specs
     * @return array<string, list<string>>
     */
    protected function blockers(array $specs): array
    {
        $open = [];

        foreach ($specs as $spec) {
            if (strtoupper((string) ($spec['status'] ?? 'TODO')) !== 'DONE') {
                $open[strtoupper((string) $spec['code'])] = $spec;
            }
        }

        $edges = [];

        foreach ($open as $code => $spec) {
            $edges[$code] = array_values(array_filter(
                SpecBlockers::read((string) ($spec['body'] ?? '')) ?? [],
                static fn (string $blocker): bool => isset($open[$blocker]) && $blocker !== $code
            ));
        }

        return $edges;
    }

    /**
     * Rings of specs that wait for each other, each from a spec back to itself.
     *
     * @param  array<string, list<string>>  $edges
     * @return list<list<string>>
     */
    protected function cycles(array $edges): array
    {
        $state = [];
        $found = [];

        $visit = function (string $code, array $path) use (&$visit, &$state, &$found, $edges): void {
            $state[$code] = 1;
            $path[] = $code;

            foreach ($edges[$code] ?? [] as $next) {
                if (($state[$next] ?? 0) === 1) {
                    $found[] = [...array_slice($path, (int) array_search($next, $path, true)), $next];
                } elseif (! isset($state[$next])) {
                    $visit($next, $path);
                }
            }

            $state[$code] = 2;
        };

        foreach (array_keys($edges) as $code) {
            if (! isset($state[$code])) {
                $visit((string) $code, []);
            }
        }

        return $found;
    }

    /**
     * The codes a row names, as a list: `[US-001, US-002]`, or one code, or
     * `US-001, US-002` on one line. A single code read as "none" would
     * clear the blockers it was meant to set.
     *
     * @return list<string>
     */
    protected function names(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\s,]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        return is_array($value)
            ? array_values(array_filter(array_map(
                static fn (mixed $name): string => is_scalar($name) ? trim((string) $name) : '',
                $value
            ), static fn (string $name): bool => $name !== '' && $name !== '-'))
            : [];
    }

    /**
     * The rows of one list of the payload, each an array.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, string>>  $findings
     * @return array<int, array<string, mixed>>
     */
    protected function rows(array $payload, string $key, array &$findings): array
    {
        $rows = $payload[$key] ?? [];

        if (! is_array($rows) || array_filter($rows, static fn (mixed $row): bool => ! is_array($row)) !== []) {
            $findings[] = $this->finding('SCHEDULE_INVALID_LIST', 'error', $key, "`{$key}` is a list of changes.", 'One entry for each thing the re-plan changes.');

            return [];
        }

        return array_values($rows);
    }

    protected function isDate(string $date): bool
    {
        return PlanDate::isDay($date);
    }

    /**
     * Days the second date falls after the first, and zero when it does not.
     */
    protected function daysPast(string $date, string $later): int
    {
        return $later > $date
            ? (int) (new DateTimeImmutable($date))->diff(new DateTimeImmutable($later))->days
            : 0;
    }

    /**
     * @return array{code: string, severity: string, path: string, message: string, hint: string}
     */
    protected function finding(string $code, string $severity, string $path, string $message, string $hint): array
    {
        return compact('code', 'severity', 'path', 'message', 'hint');
    }
}
