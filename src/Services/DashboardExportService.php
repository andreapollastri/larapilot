<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\SpecBlockers;

/**
 * Markdown downloads for the dashboard: the board as it stands, its epics
 * as an outline, the plan, one spec with everything attached to it, and
 * the PRD file itself. The words stay the project's; only the frame around
 * them is written here.
 */
class DashboardExportService
{
    public function __construct(
        protected DashboardService $dashboard,
        protected PrdService $prd,
        protected SpecService $specs,
        protected PlanService $plans,
    ) {}

    /**
     * The board, status by status. `$filters` takes the same four keys the
     * board narrows by on screen (`q`, `priority`, `epic`, `status`), so a
     * filtered board downloads as what is on screen.
     *
     * @param  array<string, mixed>  $filters
     */
    public function board(array $filters = []): string
    {
        $board = $this->dashboard->board();
        $filters = $this->boardFilters($filters);
        $columns = $this->boardColumns($board, $filters);
        $filtered = $this->isFiltered($filters);
        $done = (string) ($board['workflow']['done'] ?? 'DONE');
        $wip = is_array($board['workflow']['wip'] ?? null) ? $board['workflow']['wip'] : [];
        $totals = ['specs' => 0, 'done' => 0, 'wip' => 0, 'points' => 0, 'done_points' => 0, 'tasks' => 0, 'done_tasks' => 0];

        foreach ($columns as $status => $specs) {
            foreach ($specs as $spec) {
                $points = max(0, (int) ($spec['points'] ?? 0));
                $totals['specs']++;
                $totals['points'] += $points;
                $totals['tasks'] += (int) ($spec['tasks']['total'] ?? 0);
                $totals['done_tasks'] += (int) ($spec['tasks']['done'] ?? 0);

                if ($status === $done) {
                    $totals['done']++;
                    $totals['done_points'] += $points;
                }

                if (in_array($status, $wip, true)) {
                    $totals['wip']++;
                }
            }
        }

        $title = $this->projectTitle();
        $lines = ['# Board'.($title !== '' ? ' — '.$title : ''), ''];
        $lines[] = 'The backlog as it stood on '.now()->format('Y-m-d H:i').', grouped by workflow status.';

        if ($filtered) {
            $lines[] = '';
            $lines[] = '**Filtered by:** '.$this->describeBoardFilters($filters).'.';
        }

        $lines[] = '';
        $lines[] = '## Summary';
        $lines[] = '';
        $lines[] = '| Measure | Value |';
        $lines[] = '| --- | ---: |';
        $lines[] = '| Specs | '.$totals['specs'].' |';
        $lines[] = '| Done | '.$totals['done'].' |';
        $lines[] = '| Completion | '.$this->rate($totals['done'], $totals['specs']).' |';
        $lines[] = '| In progress or in review | '.$totals['wip'].' |';
        $lines[] = '| Story points | '.$totals['points'].' ('.$totals['done_points'].' done) |';
        $lines[] = '| Tasks | '.$totals['tasks'].' ('.$totals['done_tasks'].' done) |';
        $lines[] = '';
        $lines[] = '## By status';
        $lines[] = '';
        $lines[] = '| Status | Specs | Story points |';
        $lines[] = '| --- | ---: | ---: |';

        foreach ($columns as $status => $specs) {
            $points = array_sum(array_map(static fn (array $spec): int => max(0, (int) ($spec['points'] ?? 0)), $specs));
            $lines[] = '| '.$this->cell($status).' | '.count($specs).' | '.$points.' |';
        }

        foreach ($columns as $status => $specs) {
            $lines[] = '';
            $lines[] = '## '.$status.' ('.count($specs).')';
            $lines[] = '';

            if ($specs === []) {
                $lines[] = '_No specs._';

                continue;
            }

            $lines[] = '| Code | Title | Priority | Points | Epic | Tasks | Merged as |';
            $lines[] = '| --- | --- | --- | ---: | --- | --- | --- |';

            foreach ($specs as $spec) {
                $tasks = (int) ($spec['tasks']['total'] ?? 0);
                $points = max(0, (int) ($spec['points'] ?? 0));

                $lines[] = '| '.implode(' | ', [
                    $this->cell((string) ($spec['code'] ?? '')),
                    $this->cell((string) ($spec['title'] ?? 'Untitled')),
                    $this->cell($this->dash((string) ($spec['priority'] ?? ''))),
                    $points > 0 ? (string) $points : '—',
                    $this->cell($this->dash((string) ($spec['epic']['title'] ?? ''))),
                    $tasks > 0 ? ((int) ($spec['tasks']['done'] ?? 0)).' of '.$tasks.' done' : '—',
                    $this->commit(is_array($spec['merge_commit'] ?? null) ? $spec['merge_commit'] : null, false),
                ]).' |';
            }
        }

        return implode("\n", $lines)."\n";
    }

    public function boardFilename(): string
    {
        return $this->slug($this->projectTitle(), 'larapilot').'-board-'.now()->format('Y-m-d').'.md';
    }

    /**
     * The backlog as an outline: the project, every epic with its story
     * points, its user stories with theirs, and under each planned story
     * its tasks. A task is estimated in hours, so that is what it carries.
     * `$filters` are the ones of the board: the outline holds the stories
     * on screen, and an epic counts the points of those.
     *
     * @param  array<string, mixed>  $filters
     */
    public function epics(array $filters = []): string
    {
        $filters = $this->boardFilters($filters);
        $epics = [];
        $loose = [];

        foreach ($this->boardColumns($this->dashboard->board(), $filters) as $specs) {
            foreach ($specs as $spec) {
                $code = trim((string) ($spec['epic']['code'] ?? ''));

                if ($code === '') {
                    $loose[] = $spec;

                    continue;
                }

                $epics[$code] ??= ['title' => '', 'specs' => []];
                $epics[$code]['specs'][] = $spec;

                if ($epics[$code]['title'] === '') {
                    $epics[$code]['title'] = $this->inline((string) ($spec['epic']['title'] ?? ''));
                }
            }
        }

        uksort($epics, 'strnatcasecmp');

        $title = $this->projectTitle();
        $lines = ['# '.($title !== '' ? $title : $this->inline((string) config('app.name', 'Project')))];

        if ($this->isFiltered($filters)) {
            $lines[] = '';
            $lines[] = '**Filtered by:** '.$this->describeBoardFilters($filters).'.';
        }

        foreach ($epics as $code => $epic) {
            $name = $epic['title'] !== '' && $epic['title'] !== (string) $code ? $code.' — '.$epic['title'] : (string) $code;
            $lines = array_merge($lines, $this->epicOutline($name, $epic['specs']));
        }

        if ($loose !== []) {
            $lines = array_merge($lines, $this->epicOutline('Stories without an epic', $loose));
        }

        if ($epics === [] && $loose === []) {
            $lines[] = '';
            $lines[] = $this->isFiltered($filters)
                ? '_No story matches the filters._'
                : '_The backlog is empty. Write the stories with `/larapilot-spec`._';
        }

        return implode("\n", $lines)."\n";
    }

    public function epicsFilename(): string
    {
        return $this->slug($this->projectTitle(), 'larapilot').'-epics-'.now()->format('Y-m-d').'.md';
    }

    /**
     * One epic of the outline: its heading with the points of its stories,
     * then each story with its own and the tasks of its plan.
     *
     * @param  list<array<string, mixed>>  $specs
     * @return list<string>
     */
    protected function epicOutline(string $name, array $specs): array
    {
        usort($specs, static fn (array $left, array $right): int => strnatcasecmp((string) ($left['code'] ?? ''), (string) ($right['code'] ?? '')));

        $points = array_sum(array_map(static fn (array $spec): int => max(0, (int) ($spec['points'] ?? 0)), $specs));
        $lines = ['', '## '.$name.' ('.$points.' SP)'];

        foreach ($specs as $spec) {
            $code = (string) ($spec['code'] ?? '');
            $specPoints = max(0, (int) ($spec['points'] ?? 0));

            $lines[] = '';
            $lines[] = '#### '.$code.' — '.$this->inline((string) ($spec['title'] ?? 'Untitled')).($specPoints > 0 ? ' ('.$specPoints.' SP)' : '');

            $plan = $this->plans->read($code);
            $tasks = is_array($plan['tasks'] ?? null) ? array_values(array_filter($plan['tasks'], 'is_array')) : [];

            if ($tasks === []) {
                continue;
            }

            $lines[] = '';

            foreach ($tasks as $task) {
                $hours = (float) ($task['estimate_hours'] ?? 0);

                $lines[] = '- '.$this->inline((string) ($task['id'] ?? 'TASK')).' — '.$this->inline((string) ($task['title'] ?? 'Untitled')).($hours > 0 ? ' ('.$this->number($hours).' h)' : '');
            }
        }

        return $lines;
    }

    /**
     * One spec with everything the detail page shows: the story, the plan,
     * every task with its body, the decisions, and the comments.
     */
    public function spec(string $code): ?string
    {
        $data = $this->dashboard->spec($code);

        if ($data === null) {
            return null;
        }

        $spec = $data['spec'];
        $tasks = $data['tasks'];
        $plan = $this->plans->read($code);
        $done = count(array_filter($tasks, fn (array $task): bool => $this->isDone($task)));
        $hours = array_sum(array_map(static fn (array $task): float => (float) ($task['estimate_hours'] ?? 0), $tasks));

        $lines = ['# '.$code.' — '.$this->inline((string) ($spec['title'] ?? 'Untitled')), ''];
        $lines[] = '| Field | Value |';
        $lines[] = '| --- | --- |';

        foreach ($this->specFacts($spec, count($tasks), $done, $hours) as $label => $value) {
            $lines[] = '| '.$label.' | '.$value.' |';
        }

        $lines[] = '';
        $lines[] = '## User story';
        $lines[] = '';
        $lines[] = $this->embed((string) ($spec['body'] ?? ''), 3, '_No story written yet._');

        $planBody = is_array($plan) ? trim((string) ($plan['plan_body'] ?? '')) : '';

        if ($planBody !== '') {
            $lines[] = '';
            $lines[] = '## Technical plan';
            $lines[] = '';
            $lines[] = $this->embed($planBody, 3);
        }

        $lines[] = '';
        $lines[] = '## Tasks ('.$done.' of '.count($tasks).' done)';
        $lines[] = '';

        if ($tasks === []) {
            $lines[] = '_No plan tasks yet. Run `/larapilot-plan '.$code.'`._';
        }

        foreach ($tasks as $index => $task) {
            if ($index > 0) {
                $lines[] = '';
            }

            $lines[] = '### '.$this->inline((string) ($task['id'] ?? 'TASK')).' — '.$this->inline((string) ($task['title'] ?? 'Untitled'));
            $lines[] = '';

            foreach ($this->taskFacts($task) as $label => $value) {
                $lines[] = '- **'.$label.':** '.$value;
            }

            $body = trim((string) ($task['body'] ?? ''));

            if ($body !== '') {
                $lines[] = '';
                $lines[] = $this->embed($body, 4);
            }
        }

        $decisions = is_array($data['decisions']['entries'] ?? null) ? $data['decisions']['entries'] : [];

        if ($decisions !== []) {
            $lines[] = '';
            $lines[] = '## Decision journal';
            $lines[] = '';
            $lines[] = '| When | Topic | Decision | Why |';
            $lines[] = '| --- | --- | --- | --- |';

            foreach ($decisions as $entry) {
                $value = $this->cell((string) ($entry['value'] ?? ''));

                $lines[] = '| '.implode(' | ', [
                    $this->cell((string) ($entry['at'] ?? '')),
                    $this->cell((string) ($entry['label'] ?? $entry['topic'] ?? '')),
                    ! empty($entry['is_superseded']) ? '~~'.$value.'~~ (superseded)' : $value,
                    $this->cell($this->dash((string) ($entry['rationale'] ?? ''))),
                ]).' |';
            }
        }

        $comments = is_array($data['feedback']['entries'] ?? null) ? $data['feedback']['entries'] : [];

        if (! empty($data['feedback']['enabled']) && $comments !== []) {
            $lines[] = '';
            $lines[] = '## Internal feedback';
            $lines[] = '';
            $lines[] = '_Notes between the team. Remove this section before sending the file to a client._';

            // Stored newest first; a conversation reads oldest first.
            foreach (array_reverse($comments) as $entry) {
                $lines[] = '';
                $lines[] = '### '.$this->inline((string) ($entry['author'] ?? '')).' — '.$this->inline((string) ($entry['at'] ?? ''));
                $lines[] = '';
                $lines[] = '- **Status at the time:** '.$this->inline($this->dash((string) ($entry['status'] ?? '')));

                if (! empty($entry['blocks_merge'])) {
                    $lines[] = '- **Blocks merge:** yes';
                }

                $lines[] = '';
                $lines[] = $this->embed((string) ($entry['body'] ?? ''), 4);
            }
        }

        return rtrim(implode("\n", $lines))."\n";
    }

    public function specFilename(string $code): string
    {
        $spec = $this->specs->find($code);
        $title = is_array($spec) ? $this->slug((string) ($spec['title'] ?? ''), '') : '';

        return $code.($title !== '' ? '-'.substr($title, 0, 60) : '').'.md';
    }

    /**
     * The plan as the Plan page shows it: the forecast, the milestones, the
     * delivery order, then every epic with its stories and their tasks.
     */
    public function plan(): string
    {
        $data = $this->dashboard->plan();
        $gantt = is_array($data['gantt'] ?? null) ? $data['gantt'] : [];
        $criticality = is_array($data['criticality'] ?? null) ? $data['criticality'] : [];
        $specs = [];

        foreach ($this->specs->allSpecs() as $spec) {
            $specs[(string) ($spec['code'] ?? '')] = $spec;
        }

        $bars = [];

        // A planned spec is drawn as its tasks: its window is theirs.
        foreach (is_array($gantt['bars'] ?? null) ? $gantt['bars'] : [] as $bar) {
            if (! is_array($bar)) {
                continue;
            }

            if (($bar['type'] ?? '') === 'spec') {
                $bars[(string) $bar['id']] = $bar;

                continue;
            }

            if (($bar['type'] ?? '') !== 'task') {
                continue;
            }

            $code = explode('·', (string) $bar['id'])[0];
            $open = strtoupper((string) ($bar['status'] ?? '')) !== 'DONE' ? (float) ($bar['estimate_hours'] ?? 0) : 0.0;
            $current = $bars[$code] ?? ['start' => $bar['start'], 'end' => $bar['end'], 'remaining_hours' => 0.0, 'depends_on' => $bar['spec_depends_on'] ?? []];

            $bars[$code] = [
                'start' => min((string) $current['start'], (string) $bar['start']),
                'end' => max((string) $current['end'], (string) $bar['end']),
                'remaining_hours' => (float) $current['remaining_hours'] + $open,
                'depends_on' => $current['depends_on'],
            ];
        }

        $done = 0;
        $points = 0;
        $donePoints = 0;
        $tasks = 0;
        $doneTasks = 0;

        foreach ($specs as $code => $spec) {
            $specPoints = max(0, (int) ($spec['points'] ?? 0));
            $progress = $this->plans->taskProgress($code);
            $isDone = strtoupper((string) ($spec['status'] ?? '')) === 'DONE';
            $done += $isDone ? 1 : 0;
            $points += $specPoints;
            $donePoints += $isDone ? $specPoints : 0;
            $tasks += (int) ($progress['total'] ?? 0);
            $doneTasks += (int) ($progress['done'] ?? 0);
        }

        $epics = is_array($gantt['epics'] ?? null) ? $gantt['epics'] : [];
        $title = $this->projectTitle();
        $lines = ['# Plan'.($title !== '' ? ' — '.$title : ''), ''];
        $lines[] = 'Epics, user stories, tasks, and the delivery forecast as they stood on '.now()->format('Y-m-d H:i').'.';
        $lines[] = '';
        $lines[] = '## Summary';
        $lines[] = '';
        $lines[] = '| Measure | Value |';
        $lines[] = '| --- | ---: |';
        $lines[] = '| Epics | '.count($epics).' |';
        $lines[] = '| User stories | '.count($specs).' ('.$done.' done) |';
        $lines[] = '| Story points | '.$points.' ('.$donePoints.' done) |';
        $lines[] = '| Tasks | '.$tasks.' ('.$doneTasks.' done) |';
        $lines[] = '| Remaining | '.((int) ($criticality['remaining_points'] ?? 0)).' SP · ~'.$this->number((float) ($criticality['remaining_hours'] ?? 0)).' h · ~'.$this->number((float) ($criticality['forecast_work_days'] ?? 0)).' work-days |';
        $lines[] = '| Forecast end | '.$this->dash((string) ($criticality['forecast_end'] ?? '')).' |';

        $assumptions = is_array($gantt['assumptions'] ?? null) ? $gantt['assumptions'] : [];

        if ($assumptions !== []) {
            $lines[] = '';
            $lines[] = '_The forecast queues open work from today, one story at a time in delivery order: '.$this->number((float) ($assumptions['hours_per_day'] ?? 6)).' working hours a day, Monday to Friday; a story with no plan counts '.$this->number((float) ($assumptions['hours_per_point'] ?? 4)).' h per story point._';
        }

        $alerts = is_array($criticality['alerts'] ?? null) ? $criticality['alerts'] : [];

        if ($alerts !== []) {
            $lines[] = '';
            $lines[] = '## Alerts';
            $lines[] = '';

            foreach ($alerts as $alert) {
                $lines[] = '- **'.$this->inline((string) ($alert['label'] ?? 'Alert')).'**'.(! empty($alert['date']) ? ' ('.$alert['date'].')' : '').' — '.$this->inline((string) ($alert['message'] ?? ''));
            }
        }

        $milestones = is_array($gantt['milestones'] ?? null) ? $gantt['milestones'] : [];

        if ($milestones !== []) {
            $lines[] = '';
            $lines[] = '## Milestones';
            $lines[] = '';
            $lines[] = '| Milestone | Date | Status | Release | Note |';
            $lines[] = '| --- | --- | --- | --- | --- |';

            foreach ($milestones as $milestone) {
                $lines[] = '| '.implode(' | ', [
                    $this->cell((string) ($milestone['label'] ?? '')),
                    $this->cell($this->dash((string) ($milestone['date'] ?? ''))),
                    $this->cell(str_replace('_', ' ', (string) ($milestone['status'] ?? 'on_track'))),
                    $this->cell($this->dash((string) ($milestone['release'] ?? ''))),
                    $this->cell($this->dash((string) ($milestone['note'] ?? ''))),
                ]).' |';
            }
        }

        $queue = array_values(array_filter(
            array_map('strval', is_array($gantt['queue'] ?? null) ? $gantt['queue'] : []),
            static fn (string $code): bool => isset($specs[$code])
        ));

        if ($queue !== []) {
            $lines[] = '';
            $lines[] = '## Delivery order';
            $lines[] = '';
            $lines[] = '| # | Story | Status | Priority | Forecast | Remaining | Blocked by |';
            $lines[] = '| ---: | --- | --- | --- | --- | ---: | --- |';

            foreach ($queue as $position => $code) {
                $spec = $specs[$code];
                $bar = $bars[$code] ?? [];

                $lines[] = '| '.implode(' | ', [
                    (string) ($position + 1),
                    $this->cell($code.' — '.(string) ($spec['title'] ?? 'Untitled')),
                    $this->cell($this->dash((string) ($spec['status'] ?? ''))),
                    $this->cell($this->dash((string) ($spec['priority'] ?? ''))),
                    $this->window($bar),
                    isset($bar['remaining_hours']) ? $this->number((float) $bar['remaining_hours']).' h' : '—',
                    $this->cell($this->dash(implode(', ', array_map('strval', is_array($bar['depends_on'] ?? null) ? $bar['depends_on'] : [])))),
                ]).' |';
            }
        }

        $listed = [];

        foreach ($epics as $epic) {
            $codes = array_values(array_filter(
                array_map('strval', is_array($epic['spec_codes'] ?? null) ? $epic['spec_codes'] : []),
                static fn (string $code): bool => isset($specs[$code])
            ));
            $listed = array_merge($listed, $codes);

            $lines[] = '';
            $lines[] = '## '.$this->inline((string) ($epic['code'] ?? 'EPIC')).' — '.$this->inline((string) ($epic['title'] ?? ''));
            $lines[] = '';

            if (trim((string) ($epic['objective'] ?? '')) !== '') {
                $lines[] = $this->inline((string) $epic['objective']);
                $lines[] = '';
            }

            $lines[] = '- **Story points:** '.((int) ($epic['done_points'] ?? 0)).' of '.((int) ($epic['points'] ?? 0)).' done';
            $lines[] = '- **Forecast:** '.$this->window(['start' => $epic['start'] ?? null, 'end' => $epic['forecast_end'] ?? null]);

            if (! empty($epic['deadline'])) {
                $lines[] = '- **Deadline:** '.$epic['deadline'];
            }

            $lines = array_merge($lines, $this->planStories($codes, $specs, $bars));
        }

        $loose = array_values(array_diff(array_keys($specs), $listed));

        if ($loose !== []) {
            $lines[] = '';
            $lines[] = '## Stories without an epic';
            $lines = array_merge($lines, $this->planStories($loose, $specs, $bars));
        }

        if ($specs === []) {
            $lines[] = '';
            $lines[] = '_The backlog is empty. Write the stories with `/larapilot-spec`._';
        }

        return rtrim(implode("\n", $lines))."\n";
    }

    public function planFilename(): string
    {
        return $this->slug($this->projectTitle(), 'larapilot').'-plan-'.now()->format('Y-m-d').'.md';
    }

    /**
     * The stories of one epic: a table, then the tasks of each planned one.
     *
     * @param  list<string>  $codes
     * @param  array<string, array<string, mixed>>  $specs
     * @param  array<string, array<string, mixed>>  $bars
     * @return list<string>
     */
    protected function planStories(array $codes, array $specs, array $bars): array
    {
        $lines = ['', '| Story | Status | Priority | Points | Release | Blocked by | Tasks | Forecast |', '| --- | --- | --- | ---: | --- | --- | --- | --- |'];

        foreach ($codes as $code) {
            $spec = $specs[$code];
            $progress = $this->plans->taskProgress($code);
            $release = $spec['release'] ?? null;
            $release = is_array($release) ? (string) ($release['version'] ?? $release['name'] ?? '') : (string) $release;

            // The template writes the release as a line of the body.
            if (trim($release) === '' && preg_match('/\*\*Release:?\*\*:?\s*([^\n*]+)/i', (string) ($spec['body'] ?? ''), $line) === 1) {
                $release = trim($line[1]);
            }

            $blocked = SpecBlockers::read((string) ($spec['body'] ?? '')) ?? [];

            $lines[] = '| '.implode(' | ', [
                $this->cell($code.' — '.(string) ($spec['title'] ?? 'Untitled')),
                $this->cell($this->dash((string) ($spec['status'] ?? ''))),
                $this->cell($this->dash((string) ($spec['priority'] ?? ''))),
                max(0, (int) ($spec['points'] ?? 0)) > 0 ? (string) (int) $spec['points'] : '—',
                $this->cell($this->dash($release)),
                $this->cell($this->dash(implode(', ', $blocked))),
                (int) ($progress['total'] ?? 0) > 0 ? ((int) $progress['done']).' of '.((int) $progress['total']) : '—',
                $this->window($bars[$code] ?? []),
            ]).' |';
        }

        foreach ($codes as $code) {
            $plan = $this->plans->read($code);
            $planTasks = is_array($plan['tasks'] ?? null) ? array_values(array_filter($plan['tasks'], 'is_array')) : [];

            if ($planTasks === []) {
                continue;
            }

            $lines[] = '';
            $lines[] = '### '.$code.' — '.$this->inline((string) ($specs[$code]['title'] ?? 'Untitled'));
            $lines[] = '';
            $lines[] = '| Task | Title | Status | Type | Assignee | Estimate | Depends on |';
            $lines[] = '| --- | --- | --- | --- | --- | ---: | --- |';

            foreach ($planTasks as $task) {
                $dependencies = array_map('strval', is_array($task['dependencies'] ?? null) ? $task['dependencies'] : []);

                $lines[] = '| '.implode(' | ', [
                    $this->cell((string) ($task['id'] ?? '')),
                    $this->cell((string) ($task['title'] ?? '')),
                    $this->isDone($task) ? 'DONE' : $this->cell($this->dash((string) ($task['status'] ?? 'TODO'))),
                    $this->cell($this->dash((string) ($task['type'] ?? ''))),
                    $this->cell($this->dash((string) ($task['assignee'] ?? ''))),
                    (float) ($task['estimate_hours'] ?? 0) > 0 ? $this->number((float) $task['estimate_hours']).' h' : '—',
                    $this->cell($this->dash(implode(', ', $dependencies))),
                ]).' |';
            }
        }

        return $lines;
    }

    /**
     * `2026-10-01 → 2026-10-07`, or a dash.
     *
     * @param  array<string, mixed>  $bar
     */
    protected function window(array $bar): string
    {
        $start = is_string($bar['start'] ?? null) ? substr($bar['start'], 0, 10) : '';
        $end = is_string($bar['end'] ?? null) ? substr($bar['end'], 0, 10) : '';

        if ($start === '' && $end === '') {
            return '—';
        }

        return $start === $end || $end === '' ? $start : ($start !== '' ? $start.' → '.$end : $end);
    }

    public function prd(): ?string
    {
        $content = $this->prd->read();

        return $content === null || trim($content) === '' ? null : $content;
    }

    public function prdFilename(): string
    {
        return $this->slug($this->projectTitle(), 'product').'-prd.md';
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, string>
     */
    protected function specFacts(array $spec, int $tasks, int $done, float $hours): array
    {
        $facts = [
            'Status' => $this->cell($this->dash((string) ($spec['status'] ?? ''))),
            'Priority' => $this->cell($this->dash((string) ($spec['priority'] ?? ''))),
            'Story points' => max(0, (int) ($spec['points'] ?? 0)) > 0 ? (string) (int) $spec['points'] : '—',
        ];

        $epic = is_array($spec['epic'] ?? null) ? $spec['epic'] : [];

        if (trim((string) ($epic['title'] ?? $epic['code'] ?? '')) !== '') {
            $facts['Epic'] = $this->cell(trim(implode(' — ', array_filter([
                (string) ($epic['code'] ?? ''),
                (string) ($epic['title'] ?? ''),
            ], static fn (string $part): bool => $part !== ''))));
        }

        if (trim((string) ($epic['objective'] ?? '')) !== '') {
            $facts['Epic objective'] = $this->cell((string) $epic['objective']);
        }

        if (trim((string) ($epic['deadline'] ?? '')) !== '') {
            $facts['Epic deadline'] = $this->cell((string) $epic['deadline']);
        }

        $release = $spec['release'] ?? null;
        $release = is_array($release) ? (string) ($release['version'] ?? $release['name'] ?? '') : (string) $release;

        if (trim($release) !== '') {
            $facts['Release'] = $this->cell($release);
        }

        $facts['Tasks'] = $tasks > 0 ? $done.' of '.$tasks.' done' : 'Not planned yet';

        if ($hours > 0) {
            $facts['Estimated hours'] = $this->number($hours).' h';
        }

        if (is_array($spec['merge_commit'] ?? null)) {
            $facts['Merged as'] = $this->commit($spec['merge_commit'], true);
        }

        $facts['Exported'] = now()->format('Y-m-d H:i');

        return $facts;
    }

    /**
     * @param  array<string, mixed>  $task
     * @return array<string, string>
     */
    protected function taskFacts(array $task): array
    {
        $facts = ['Status' => $this->isDone($task) ? 'DONE' : $this->inline($this->dash((string) ($task['status'] ?? 'TODO')))];

        foreach (['type' => 'Type', 'assignee' => 'Assignee'] as $key => $label) {
            if (trim((string) ($task[$key] ?? '')) !== '') {
                $facts[$label] = $this->inline((string) $task[$key]);
            }
        }

        if ((float) ($task['estimate_hours'] ?? 0) > 0) {
            $facts['Estimate'] = $this->number((float) $task['estimate_hours']).' h';
        }

        $dependencies = array_values(array_filter(
            array_map('strval', is_array($task['dependencies'] ?? null) ? $task['dependencies'] : []),
            static fn (string $id): bool => trim($id) !== ''
        ));

        if ($dependencies !== []) {
            $facts['Depends on'] = $this->inline(implode(', ', $dependencies));
        }

        if (! empty($task['parallel'])) {
            $facts['Can run in parallel'] = 'yes';
        }

        if (is_array($task['commit'] ?? null)) {
            $facts['Commit'] = $this->commit($task['commit'], true);
        }

        return $facts;
    }

    /**
     * @param  array<string, mixed>|null  $commit
     */
    protected function commit(?array $commit, bool $withSubject): string
    {
        $sha = (string) ($commit['short_sha'] ?? substr((string) ($commit['sha'] ?? ''), 0, 7));

        if ($sha === '') {
            return '—';
        }

        $url = (string) ($commit['url'] ?? '');
        $label = $url !== '' ? '[`'.$sha.'`]('.$url.')' : '`'.$sha.'`';
        $subject = trim((string) ($commit['subject'] ?? ''));

        return $withSubject && $subject !== '' ? $label.' — '.$this->cell($subject) : $label;
    }

    /**
     * A body written on its own starts its headings wherever the author
     * liked. Placed under a heading of this file they are moved so the
     * highest one sits at `$level` and the outline stays in order.
     */
    protected function embed(string $markdown, int $level, string $empty = ''): string
    {
        $markdown = trim(str_replace(["\r\n", "\r"], "\n", $markdown));

        if ($markdown === '') {
            return $empty;
        }

        $lines = explode("\n", $markdown);
        $headings = [];
        $inFence = false;

        foreach ($lines as $index => $line) {
            if (preg_match('/^\s*(?:```|~~~)/', $line) === 1) {
                $inFence = ! $inFence;

                continue;
            }

            if (! $inFence && preg_match('/^(#{1,6})\s+\S/', $line, $matches) === 1) {
                $headings[$index] = strlen($matches[1]);
            }
        }

        if ($headings === []) {
            return $markdown;
        }

        $shift = $level - min($headings);

        foreach ($headings as $index => $depth) {
            $lines[$index] = str_repeat('#', max(1, min(6, $depth + $shift))).substr($lines[$index], $depth);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{q: string, priority: string, epic: string, status: string}
     */
    protected function boardFilters(array $filters): array
    {
        $read = static fn (string $key): string => is_scalar($filters[$key] ?? null)
            ? trim(substr((string) $filters[$key], 0, 200))
            : '';

        return [
            'q' => strtolower($read('q')),
            'priority' => strtoupper($read('priority')),
            'epic' => $read('epic'),
            'status' => $read('status'),
        ];
    }

    /**
     * The columns the filters leave on screen, in the order of the board.
     *
     * @param  array<string, mixed>  $board
     * @param  array{q: string, priority: string, epic: string, status: string}  $filters
     * @return array<string, list<array<string, mixed>>>
     */
    protected function boardColumns(array $board, array $filters): array
    {
        $columns = [];

        foreach ($board['statusOrder'] as $status) {
            if ($filters['status'] !== '' && $status !== $filters['status']) {
                continue;
            }

            $columns[$status] = array_values(array_filter(
                $board['columns'][$status] ?? [],
                fn (array $spec): bool => $this->matchesBoardFilters($spec, $filters)
            ));
        }

        return $columns;
    }

    /**
     * @param  array{q: string, priority: string, epic: string, status: string}  $filters
     */
    protected function isFiltered(array $filters): bool
    {
        return array_filter($filters, static fn (string $value): bool => $value !== '') !== [];
    }

    /**
     * The same test the board runs in the browser, on the same fields.
     *
     * @param  array<string, mixed>  $spec
     * @param  array{q: string, priority: string, epic: string, status: string}  $filters
     */
    protected function matchesBoardFilters(array $spec, array $filters): bool
    {
        if ($filters['priority'] !== '' && strtoupper(trim((string) ($spec['priority'] ?? ''))) !== $filters['priority']) {
            return false;
        }

        if ($filters['epic'] !== '' && (string) ($spec['epic']['code'] ?? '') !== $filters['epic']) {
            return false;
        }

        if ($filters['q'] === '') {
            return true;
        }

        $haystack = strtolower(implode(' ', [
            (string) ($spec['code'] ?? ''),
            (string) ($spec['title'] ?? ''),
            (string) ($spec['priority'] ?? ''),
            (string) ($spec['epic']['code'] ?? ''),
            (string) ($spec['epic']['title'] ?? ''),
            (string) ($spec['merge_commit']['short_sha'] ?? ''),
            (string) ($spec['merge_commit']['subject'] ?? ''),
        ]));

        return str_contains($haystack, $filters['q']);
    }

    /**
     * @param  array{q: string, priority: string, epic: string, status: string}  $filters
     */
    protected function describeBoardFilters(array $filters): string
    {
        $parts = [];

        foreach (['q' => 'search', 'priority' => 'priority', 'epic' => 'epic', 'status' => 'status'] as $key => $label) {
            if ($filters[$key] !== '') {
                $parts[] = $label.' “'.$this->inline($filters[$key]).'”';
            }
        }

        return implode(', ', $parts);
    }

    /**
     * @param  array<string, mixed>  $task
     */
    protected function isDone(array $task): bool
    {
        return strtoupper((string) ($task['status'] ?? '')) === 'DONE';
    }

    protected function rate(int $part, int $whole): string
    {
        if ($whole === 0) {
            return '0%';
        }

        return $this->number(round($part / $whole * 100, 1)).'%';
    }

    protected function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }

    protected function dash(string $value): string
    {
        return trim($value) === '' ? '—' : trim($value);
    }

    /**
     * One line of text that cannot break out of the line it sits on.
     */
    protected function inline(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Text for a table cell: one line, and a pipe that stays a character.
     */
    protected function cell(string $text): string
    {
        return str_replace('|', '\\|', $this->inline($text));
    }

    protected function projectTitle(): string
    {
        $prd = $this->prd->read();

        if (is_string($prd) && preg_match('/^#\s+(.+)$/m', $prd, $matches) === 1) {
            return $this->inline((string) preg_replace('/[*_`]/', '', $matches[1]));
        }

        return '';
    }

    protected function slug(string $title, string $fallback): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $ascii !== false ? $ascii : $title), '-'));

        return $slug !== '' ? $slug : $fallback;
    }
}
