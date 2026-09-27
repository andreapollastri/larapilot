@extends('larapilot::dashboard.layout')

@section('title', 'Plan')

@section('main-class', 'is-wide')

@push('styles')
<style>
    .plan-page {
        display: flex;
        flex-direction: column;
        gap: 18px;
        /* between the amber of work in progress and the red of a missed date */
        --risk: color-mix(in srgb, var(--danger-fill) 55%, var(--warn-fill));
    }
    .plan-page .page-head,
    .plan-page .metrics { margin-bottom: 0; }

    .health {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 36px;
        padding: 0 14px;
        border: 1px solid var(--border);
        border-radius: 999px;
        background: var(--surface);
        font-size: 0.82rem;
        font-weight: 600;
        white-space: nowrap;
        --tone: var(--ok-fill);
        border-color: color-mix(in srgb, var(--tone) 45%, var(--border));
    }

    .health .dot {
        width: 8px;
        height: 8px;
        border-radius: 999px;
        background: var(--tone);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--tone) 22%, transparent);
    }

    .health.ok { color: var(--ok); }
    .health.at-risk { --tone: var(--warn-fill); color: var(--warn); }
    .health.late { --tone: var(--danger-fill); color: var(--danger); }

    .plan-page .metric-value { font-size: 1.4rem; }
    .metric-value.is-date { font-size: 1.08rem; letter-spacing: -0.01em; }
    .metric-unit { color: var(--muted); font-size: 0.85rem; font-weight: 600; letter-spacing: 0; }

    .alerts { display: grid; gap: 8px; }

    .alert {
        padding: 12px 16px;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        font-size: 0.86rem;
        line-height: 1.45;
        --tone: var(--warn-fill);
        border-color: color-mix(in srgb, var(--tone) 45%, var(--border));
        background: color-mix(in srgb, var(--tone) 9%, var(--surface));
    }

    .alert strong { font-weight: 600; }
    .alert.critical { --tone: var(--danger-fill); }
    .alert.warning { --tone: var(--warn-fill); }
    .alert .when { color: var(--muted); font-variant-numeric: tabular-nums; }

    .milestones {
        display: flex;
        gap: 10px;
        margin: 0 calc(var(--gutter) * -1);
        padding: 2px var(--gutter) 6px;
        overflow-x: auto;
        scrollbar-width: thin;
    }

    .milestone-card {
        flex: 0 0 auto;
        min-width: 190px;
        max-width: 260px;
        padding: 14px 16px;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--surface);
    }

    .milestone-card .date {
        color: var(--muted);
        font-size: 0.74rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }

    .milestone-card strong {
        display: block;
        margin-top: 4px;
        font-size: 0.92rem;
        font-weight: 600;
    }

    .milestone-card .state {
        display: inline-block;
        margin-top: 8px;
        font-size: 0.68rem;
        font-weight: 650;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .milestone-card.on_track .state { color: var(--ok); }
    .milestone-card.at_risk .state { color: var(--warn); }
    .milestone-card.delayed .state { color: var(--danger); }
    .milestone-card.done .state { color: var(--muted); }

    .milestone-card .note {
        margin: 6px 0 0;
        color: var(--muted);
        font-size: 0.78rem;
        line-height: 1.4;
    }

    .chart-card { padding: 16px 14px 14px; }

    @media (min-width: 640px) {
        .chart-card { padding: 20px 20px 16px; }
    }

    .plan-tools {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: end;
        margin-bottom: 14px;
    }

    .plan-tools .field { flex: 1 1 calc(50% - 6px); }
    .plan-tools .field:first-child { flex-basis: 100%; }

    @media (min-width: 860px) {
        .plan-tools .field { flex: 0 1 190px; }
        .plan-tools .field:first-child { flex: 0 1 260px; }
    }

    .plan-tools .check {
        display: flex;
        align-items: center;
        gap: 8px;
        min-height: 36px;
        color: var(--text);
        font-size: 0.86rem;
        font-weight: 500;
        cursor: pointer;
    }

    .plan-tools .check input { accent-color: var(--accent); width: 16px; height: 16px; margin: 0; }

    .plan-count {
        margin-left: auto;
        min-height: 36px;
        display: inline-flex;
        align-items: center;
        color: var(--muted);
        font-size: 0.78rem;
        font-variant-numeric: tabular-nums;
    }

    .chart-scroll {
        --label: 292px;
        overflow-x: auto;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background:
            linear-gradient(var(--surface), var(--surface)) 0 0 / var(--label) 100% no-repeat,
            var(--surface-2);
        scrollbar-width: thin;
    }

    .chart {
        --label: 292px;
        --row: 38px;
        min-width: calc(var(--label) + var(--track));
    }

    /* a phone keeps most of its width for the timeline */
    @media (max-width: 640px) {
        .chart-scroll, .chart { --label: 168px; }
    }

    .gantt-head,
    .gantt-row {
        display: grid;
        grid-template-columns: var(--label) minmax(0, 1fr);
        align-items: stretch;
    }

    .gantt-row[hidden] { display: none !important; }

    .gantt-head {
        position: sticky;
        top: 0;
        z-index: 3;
        background: var(--surface);
        border-bottom: 1px solid var(--border);
    }

    .label-col {
        position: sticky;
        left: 0;
        z-index: 2;
        display: flex;
        align-items: center;
        gap: 8px;
        min-height: var(--row);
        padding: 6px 12px;
        background: var(--surface);
        border-right: 1px solid var(--border);
    }

    .gantt-head .label-col {
        color: var(--muted);
        font-size: 0.7rem;
        font-weight: 650;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .scale {
        position: relative;
        height: 36px;
    }

    .tick {
        position: absolute;
        top: 0;
        bottom: 0;
        transform: translateX(-50%);
        display: flex;
        align-items: flex-end;
        padding-bottom: 6px;
        color: var(--muted);
        font-size: 0.7rem;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
        pointer-events: none;
    }

    .tick::before {
        content: '';
        position: absolute;
        left: 50%;
        top: 0;
        bottom: 0;
        width: 1px;
        background: var(--border);
    }

    .today-flag {
        position: absolute;
        top: 0;
        bottom: 0;
        width: 0;
        z-index: 2;
        border-left: 2px solid var(--danger-fill);
    }

    .today-flag span {
        position: absolute;
        top: 4px;
        left: 4px;
        padding: 0 5px;
        border-radius: 4px;
        background: var(--surface);
        color: var(--danger);
        font-size: 0.64rem;
        font-weight: 650;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .gantt-row { border-bottom: 1px solid color-mix(in srgb, var(--border) 70%, transparent); }
    .gantt-row:last-child { border-bottom: 0; }
    .gantt-row.is-epic { background: color-mix(in srgb, var(--accent) 5%, transparent); }
    .gantt-row.is-epic .label-col { background: color-mix(in srgb, var(--accent) 5%, var(--surface)); font-weight: 600; }
    .gantt-row.is-spec .label-col { padding-left: 28px; }
    .gantt-row.is-task .label-col { padding-left: 46px; }

    .gantt-row.is-lane .label-col {
        color: var(--muted);
        font-size: 0.72rem;
        font-weight: 650;
        letter-spacing: 0.07em;
        text-transform: uppercase;
    }

    @media (max-width: 640px) {
        .gantt-row.is-spec .label-col { padding-left: 18px; }
        .gantt-row.is-task .label-col { padding-left: 28px; }
    }

    .fold {
        flex: 0 0 auto;
        width: 24px;
        height: 24px;
        padding: 0;
        border: 1px solid var(--border-strong);
        border-radius: 7px;
        background: var(--surface);
        color: var(--text-2);
        cursor: pointer;
        font-size: 0.7rem;
        line-height: 1;
    }

    .fold:hover { border-color: var(--accent); color: var(--accent); }

    .name {
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 1px;
    }

    .name .title {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 0.82rem;
    }

    .name .meta {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: var(--muted);
        font-size: 0.7rem;
        font-weight: 450;
    }

    .track {
        position: relative;
        min-height: var(--row);
        overflow: hidden;
    }

    .gridline,
    .today-line {
        position: absolute;
        top: 0;
        bottom: 0;
        width: 1px;
        background: color-mix(in srgb, var(--border) 80%, transparent);
        pointer-events: none;
    }

    .today-line { width: 2px; background: var(--danger-fill); z-index: 1; }

    .bar {
        position: absolute;
        top: 9px;
        height: 20px;
        border-radius: 6px;
        min-width: 8px;
        background: color-mix(in srgb, var(--status-todo) 75%, transparent);
        z-index: 1;
    }

    .bar.todo { background: color-mix(in srgb, var(--status-todo) 75%, transparent); }
    .bar.planned { background: color-mix(in srgb, var(--status-planned) 80%, transparent); }
    .bar.progress { background: color-mix(in srgb, var(--status-progress) 85%, transparent); }
    .bar.review { background: color-mix(in srgb, var(--status-review) 80%, transparent); }
    .bar.done { background: color-mix(in srgb, var(--status-done) 80%, transparent); }
    .bar.risk { background: color-mix(in srgb, var(--risk) 85%, transparent); }

    .bar.epic {
        top: 11px;
        height: 16px;
        border-radius: 5px;
        background: color-mix(in srgb, var(--accent) 20%, transparent);
        box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--accent) 55%, transparent);
        min-width: 10px;
    }

    .bar.epic.risk {
        background: color-mix(in srgb, var(--risk) 20%, transparent);
        box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--risk) 60%, transparent);
    }

    .bar.epic.done {
        background: color-mix(in srgb, var(--status-done) 20%, transparent);
        box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--status-done) 55%, transparent);
    }

    .bar.task { top: 12px; height: 14px; border-radius: 5px; }
    .bar.parallel { box-shadow: inset 0 0 0 1.5px var(--sky-fill); }

    .bar .fill {
        position: absolute;
        inset: 0 auto 0 0;
        background: rgba(255, 255, 255, 0.3);
        border-radius: inherit;
    }

    .bar.epic .fill { display: none; }

    .deadline {
        position: absolute;
        top: 50%;
        width: 10px;
        height: 10px;
        margin-left: -5px;
        transform: translateY(-50%) rotate(45deg);
        background: var(--warn-fill);
        border: 2px solid var(--surface);
        z-index: 2;
    }

    .diamond {
        position: absolute;
        top: 50%;
        width: 11px;
        height: 11px;
        margin-left: -6px;
        transform: translateY(-50%) rotate(45deg);
        background: var(--status-planned);
        border: 2px solid var(--surface);
        z-index: 2;
        padding: 0;
        cursor: default;
    }

    .diamond.on_track { background: var(--status-done); }
    .diamond.at_risk { background: var(--warn-fill); }
    .diamond.delayed { background: var(--danger-fill); }
    .diamond.done { background: var(--status-todo); }

    .tip {
        position: absolute;
        left: 0;
        bottom: calc(100% + 8px);
        width: max-content;
        max-width: 280px;
        padding: 9px 11px;
        border-radius: var(--radius-xs);
        background: var(--text);
        color: var(--bg);
        font-size: 0.75rem;
        line-height: 1.4;
        font-weight: 450;
        white-space: normal;
        box-shadow: var(--shadow-lg);
        opacity: 0;
        pointer-events: none;
        transform: translateY(4px);
        transition: opacity 0.12s ease, transform 0.12s ease;
        z-index: 5;
    }

    .bar:hover .tip,
    .bar:focus-visible .tip,
    .diamond:hover .tip,
    .diamond:focus-visible .tip,
    .deadline:hover .tip {
        opacity: 1;
        transform: none;
    }

    .tip strong { display: block; font-weight: 600; margin-bottom: 2px; }

    .legend {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 16px;
        margin-top: 14px;
        color: var(--muted);
        font-size: 0.75rem;
    }

    .legend i {
        display: inline-block;
        width: 16px;
        height: 8px;
        margin-right: 6px;
        border-radius: 3px;
        vertical-align: middle;
    }

    .legend i.todo { background: color-mix(in srgb, var(--status-todo) 75%, transparent); }
    .legend i.planned { background: color-mix(in srgb, var(--status-planned) 80%, transparent); }
    .legend i.progress { background: color-mix(in srgb, var(--status-progress) 85%, transparent); }
    .legend i.review { background: color-mix(in srgb, var(--status-review) 80%, transparent); }
    .legend i.done { background: color-mix(in srgb, var(--status-done) 80%, transparent); }
    .legend i.risk { background: color-mix(in srgb, var(--risk) 85%, transparent); }

    .legend i.epic {
        background: color-mix(in srgb, var(--accent) 20%, transparent);
        box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--accent) 55%, transparent);
    }

    .legend i.parallel { box-shadow: inset 0 0 0 1.5px var(--sky-fill); background: transparent; }

    .legend i.milestone {
        width: 8px;
        height: 8px;
        transform: rotate(45deg);
        background: var(--status-planned);
        border-radius: 1px;
    }

    .epic-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 270px), 1fr));
        gap: 14px;
    }

    .epic-card { padding: 18px 20px; }

    .epic-card .kicker {
        color: var(--accent);
        font-family: var(--mono);
        font-size: 0.72rem;
        font-weight: 600;
    }

    .epic-card h3 { margin: 4px 0 6px; font-size: 1rem; }
    .epic-card p { margin: 0; color: var(--muted); font-size: 0.84rem; line-height: 1.5; }

    .epic-card dl {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 5px 14px;
        margin: 14px 0 0;
        padding-top: 12px;
        border-top: 1px solid var(--border);
        font-size: 0.82rem;
    }

    .epic-card dt { color: var(--muted); }
    .epic-card dd { margin: 0; text-align: right; font-variant-numeric: tabular-nums; }
    .epic-card .slip { color: var(--warn); font-weight: 600; }
    .epic-card .chips { margin-top: 12px; }
    .epic-card .chip { padding: 2px 9px; font-family: var(--mono); font-size: 0.7rem; }

    .notes { padding: 18px 20px; }
    .notes h3 { margin: 0 0 12px; font-size: 1rem; }
    .notes ul { margin: 0; padding: 0; list-style: none; display: grid; gap: 8px; }

    .notes li {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 2px;
        padding-bottom: 8px;
        border-bottom: 1px solid var(--border);
        font-size: 0.84rem;
    }

    @media (min-width: 720px) {
        .notes li { grid-template-columns: 120px 92px minmax(0, 1fr); gap: 12px; }
    }

    .notes li:last-child { border-bottom: 0; padding-bottom: 0; }
    .notes time, .notes .tag { color: var(--muted); font-variant-numeric: tabular-nums; }

    .empty-plan { padding: 44px 24px; text-align: center; }
    .empty-plan h3 { margin: 0 0 8px; }
    .empty-plan p { margin: 0 auto; max-width: 62ch; color: var(--muted); }

    @media (max-width: 720px) {
        .plan-count { margin-left: 0; }
    }
</style>
@endpush

@section('content')
    @php
        $gantt = $gantt ?? ['bars' => [], 'milestones' => [], 'project_start' => null, 'project_end' => null, 'epics' => [], 'assignees' => []];
        $criticality = $criticality ?? ['alerts' => [], 'on_track' => true];
        $schedule = $schedule ?? ['notes' => []];
        $bars = is_array($gantt['bars'] ?? null) ? $gantt['bars'] : [];
        $epics = is_array($gantt['epics'] ?? null) ? $gantt['epics'] : [];
        $milestones = is_array($gantt['milestones'] ?? null) ? $gantt['milestones'] : [];
        $assignees = is_array($gantt['assignees'] ?? null) ? $gantt['assignees'] : [];
        $notes = is_array($schedule['notes'] ?? null) ? $schedule['notes'] : [];
        $alerts = is_array($criticality['alerts'] ?? null) ? $criticality['alerts'] : [];
        $start = $gantt['project_start'] ?? null;
        $end = $gantt['project_end'] ?? null;
        $span = 1;

        if ($start && $end) {
            $span = max(1, (new \DateTimeImmutable($end))->diff(new \DateTimeImmutable($start))->days + 1);
        }

        $pretty = function (?string $date): string {
            if ($date === null || $date === '') {
                return '—';
            }

            try {
                return (new \DateTimeImmutable(substr($date, 0, 10)))->format('j M Y');
            } catch (\Exception) {
                return $date;
            }
        };

        $statusClass = function (string $status): string {
            $s = strtolower($status);

            return match (true) {
                str_contains($s, 'done') => 'done',
                str_contains($s, 'risk') => 'risk',
                str_contains($s, 'review') => 'review',
                str_contains($s, 'progress') => 'progress',
                str_contains($s, 'planned') => 'planned',
                default => 'todo',
            };
        };

        $statusLabel = function (string $status) use ($statusClass): string {
            return match ($statusClass($status)) {
                'done' => 'Done',
                'risk' => 'At risk',
                'review' => 'Review',
                'progress' => 'In progress',
                'planned' => 'Planned',
                default => 'To do',
            };
        };

        $offsetPct = function (?string $date) use ($start, $span): float {
            if (! $date || ! $start) {
                return 0;
            }

            $days = (new \DateTimeImmutable(substr($date, 0, 10)))->diff(new \DateTimeImmutable($start))->days;

            return min(100, max(0, ($days / $span) * 100));
        };

        $widthPct = function (?string $from, ?string $to) use ($span, $offsetPct): float {
            if (! $from || ! $to) {
                return 2.5;
            }

            $days = max(1, (new \DateTimeImmutable(substr($to, 0, 10)))->diff(new \DateTimeImmutable(substr($from, 0, 10)))->days + 1);
            $width = min(100, max(1.2, ($days / $span) * 100));
            $left = $offsetPct($from);

            return min($width, max(1.2, 100 - $left));
        };

        $ticks = [];

        if ($start && $end) {
            $startDt = new \DateTimeImmutable($start);
            $endDt = new \DateTimeImmutable($end);

            if ($span <= 21) {
                $step = $span <= 10 ? 1 : 2;

                for ($i = 0; $i <= $span; $i += $step) {
                    $ticks[] = [
                        'offset' => min(100, ($i / $span) * 100),
                        'label' => $startDt->modify('+'.$i.' days')->format('j M'),
                    ];
                }
            } elseif ($span <= 120) {
                $cursor = $startDt->modify('monday this week');

                if ($cursor < $startDt) {
                    $cursor = $cursor->modify('+7 days');
                }

                while ($cursor <= $endDt) {
                    $days = $startDt->diff($cursor)->days;
                    $ticks[] = [
                        'offset' => min(100, ($days / $span) * 100),
                        'label' => $cursor->format('j M'),
                    ];
                    $cursor = $cursor->modify('+7 days');
                }
            } else {
                $cursor = $startDt->modify('first day of this month');

                if ($cursor < $startDt) {
                    $cursor = $cursor->modify('first day of next month');
                }

                while ($cursor <= $endDt) {
                    $days = $startDt->diff($cursor)->days;
                    $ticks[] = [
                        'offset' => min(100, ($days / $span) * 100),
                        'label' => $cursor->format('M Y'),
                    ];
                    $cursor = $cursor->modify('first day of next month');
                }
            }
        }

        $today = new \DateTimeImmutable('today');
        $todayOffset = null;

        if ($start && $end) {
            $startDt = new \DateTimeImmutable($start);
            $endDt = new \DateTimeImmutable($end);

            if ($today >= $startDt && $today <= $endDt) {
                $todayOffset = min(100, ($startDt->diff($today)->days / $span) * 100);
            }
        }

        $dayPx = match (true) {
            $span <= 21 => 32,
            $span <= 60 => 20,
            $span <= 180 => 11,
            default => 6,
        };
        $trackMin = max(560, (int) round($span * $dayPx));

        $tasksBySpec = [];
        $specBars = [];

        foreach ($bars as $bar) {
            if (! is_array($bar)) {
                continue;
            }

            $type = (string) ($bar['type'] ?? '');

            if ($type === 'task') {
                $id = (string) ($bar['id'] ?? '');
                $specCode = str_contains($id, '·') ? explode('·', $id, 2)[0] : $id;
                $tasksBySpec[$specCode][] = $bar;
            } elseif ($type === 'spec') {
                $specBars[(string) ($bar['id'] ?? '')] = $bar;
            }
        }

        $specNode = function (string $code, ?array $specBar, array $tasks) use ($statusClass): array {
            $rangeStart = null;
            $rangeEnd = null;
            $progress = [];
            $people = [];
            $hours = 0.0;

            if (is_array($specBar)) {
                $rangeStart = $specBar['start'] ?? null;
                $rangeEnd = $specBar['end'] ?? null;
                $progress[] = (float) ($specBar['progress'] ?? 0);
            }

            foreach ($tasks as $task) {
                $taskStart = $task['start'] ?? null;
                $taskEnd = $task['end'] ?? null;
                $rangeStart = $rangeStart === null ? $taskStart : ($taskStart !== null ? min($rangeStart, $taskStart) : $rangeStart);
                $rangeEnd = $rangeEnd === null ? $taskEnd : ($taskEnd !== null ? max($rangeEnd, $taskEnd) : $rangeEnd);
                $progress[] = (float) ($task['progress'] ?? 0);
                $hours += (float) ($task['estimate_hours'] ?? 0);

                if (! empty($task['assignee'])) {
                    $people[] = (string) $task['assignee'];
                }
            }

            $people = array_values(array_unique($people));
            $label = is_array($specBar) ? (string) ($specBar['label'] ?? $code) : $code;

            if (! is_array($specBar)) {
                $specTitle = trim((string) ($tasks[0]['spec_title'] ?? ''));

                if ($specTitle !== '') {
                    $label = $code.' — '.$specTitle;
                }
            }
            $status = is_array($specBar)
                ? (string) ($specBar['status'] ?? 'TODO')
                : (string) ($tasks[0]['status'] ?? 'TODO');

            return [
                'code' => $code,
                'label' => $label,
                'status' => $status,
                'status_class' => $statusClass($status),
                'start' => $rangeStart,
                'end' => $rangeEnd,
                'progress' => $progress === [] ? 0 : array_sum($progress) / count($progress),
                'points' => is_array($specBar) ? ($specBar['points'] ?? null) : null,
                'assignees' => $people,
                'hours' => $hours > 0 ? round($hours, 1) : null,
                'tasks' => $tasks,
                'leaf' => $tasks === [],
            ];
        };

        $claimed = [];
        $groups = [];

        foreach ($epics as $epic) {
            if (! is_array($epic) || empty($epic['code'])) {
                continue;
            }

            $children = [];

            foreach ($epic['spec_codes'] ?? [] as $code) {
                $code = (string) $code;
                $tasks = $tasksBySpec[$code] ?? [];
                $specBar = $specBars[$code] ?? null;

                if ($tasks === [] && $specBar === null) {
                    continue;
                }

                $children[] = $specNode($code, is_array($specBar) ? $specBar : null, $tasks);
                $claimed[$code] = true;
            }

            $epicStatus = ! empty($epic['deadline']) && ! empty($epic['forecast_end']) && $epic['forecast_end'] > $epic['deadline']
                ? 'AT RISK'
                : 'PLANNED';

            $groups[] = [
                'id' => (string) $epic['code'],
                'loose' => false,
                'epic' => $epic,
                'status' => $epicStatus,
                'status_class' => $statusClass($epicStatus),
                'children' => $children,
            ];
        }

        $looseChildren = [];

        foreach (array_unique(array_merge(array_keys($specBars), array_keys($tasksBySpec))) as $code) {
            if (isset($claimed[$code])) {
                continue;
            }

            $looseChildren[] = $specNode(
                (string) $code,
                isset($specBars[$code]) && is_array($specBars[$code]) ? $specBars[$code] : null,
                $tasksBySpec[$code] ?? []
            );
        }

        if ($looseChildren !== []) {
            $looseStart = null;
            $looseEnd = null;

            foreach ($looseChildren as $child) {
                if ($child['start'] !== null) {
                    $looseStart = $looseStart === null ? $child['start'] : min($looseStart, $child['start']);
                }

                if ($child['end'] !== null) {
                    $looseEnd = $looseEnd === null ? $child['end'] : max($looseEnd, $child['end']);
                }
            }

            $groups[] = [
                'id' => '__loose',
                'loose' => true,
                'epic' => [
                    'code' => '',
                    'title' => 'No epic',
                    'objective' => null,
                    'deadline' => null,
                    'start' => $looseStart,
                    'forecast_end' => $looseEnd,
                    'points' => 0,
                    'spec_codes' => array_column($looseChildren, 'code'),
                ],
                'status' => 'PLANNED',
                'status_class' => 'planned',
                'children' => $looseChildren,
            ];
        }

        $taskCount = 0;

        foreach ($groups as $group) {
            foreach ($group['children'] as $child) {
                $taskCount += count($child['tasks']);
            }
        }

        $hasChart = $groups !== [] || $milestones !== [];
        $critical = collect($alerts)->contains(fn ($alert) => ($alert['level'] ?? '') === 'critical');
        $healthClass = ($criticality['on_track'] ?? true) && $alerts === []
            ? 'ok'
            : ($critical ? 'late' : 'at-risk');
        $healthLabel = $healthClass === 'ok' ? 'On track' : ($healthClass === 'late' ? 'Behind' : 'At risk');
        $milestoneLabel = function (string $status): string {
            return match ($status) {
                'at_risk' => 'At risk',
                'delayed' => 'Delayed',
                'done' => 'Done',
                default => 'On track',
            };
        };
    @endphp

    <div class="plan-page">
        <header class="page-head">
            <div>
                <h2>Plan</h2>
                <p class="sub">Epics, deadlines, and a dependency-aware Gantt from specs and plans. Token spend stays on <a href="{{ route('larapilot.dashboard.usage') }}">Usage</a>.</p>
            </div>
            <div @class(['health', $healthClass])>
                <span class="dot" aria-hidden="true"></span>
                {{ $healthLabel }}
            </div>
        </header>

        <div class="metrics">
            <div class="card metric">
                <div class="metric-label">Window</div>
                <div class="metric-value is-date">{{ $pretty($start) }}</div>
                <div class="metric-note">through {{ $pretty($end) }} · {{ $span }} days</div>
            </div>
            <div class="card metric">
                <div class="metric-label">Remaining</div>
                <div class="metric-value">{{ $criticality['remaining_points'] ?? 0 }} <span class="metric-unit">SP</span></div>
                <div class="metric-note">~{{ $criticality['remaining_hours'] ?? 0 }} h · ~{{ $criticality['forecast_work_days'] ?? 0 }} work-days</div>
            </div>
            <div class="card metric">
                <div class="metric-label">Forecast end</div>
                <div class="metric-value is-date">{{ $pretty($criticality['forecast_end'] ?? null) }}</div>
                <div class="metric-note">from remaining effort, not the calendar window</div>
            </div>
            <div class="card metric">
                <div class="metric-label">Scope</div>
                <div class="metric-value">{{ count($epics) }} <span class="metric-unit">epics</span></div>
                <div class="metric-note">{{ $taskCount }} tasks · {{ count($milestones) }} milestones</div>
            </div>
        </div>

        @if ($alerts !== [])
            <div class="alerts">
                @foreach ($alerts as $alert)
                    <div @class(['alert', $alert['level'] ?? 'warning'])>
                        <strong>{{ $alert['label'] ?? 'Alert' }}</strong>
                        @if (! empty($alert['date']))
                            <span class="when"> · {{ $pretty($alert['date']) }}</span>
                        @endif
                        <div>{{ $alert['message'] ?? '' }}</div>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($milestones !== [])
            <div class="milestones" aria-label="Milestones">
                @foreach ($milestones as $milestone)
                    <article @class(['milestone-card', $milestone['status'] ?? 'on_track'])>
                        <div class="date">{{ $pretty($milestone['date'] ?? null) }}</div>
                        <strong>{{ $milestone['label'] ?? 'Deadline' }}</strong>
                        <span class="state">{{ $milestoneLabel((string) ($milestone['status'] ?? 'on_track')) }}</span>
                        @if (! empty($milestone['note']))
                            <p class="note">{{ $milestone['note'] }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif

        @if (! $hasChart)
            <section class="card empty-plan">
                <h3>Nothing scheduled yet</h3>
                <p>Specs become rows here as soon as they exist. A plan with tasks, dependencies, and assignees turns each story into a Gantt. Deadlines are recorded with <code>larapilot:schedule-set</code>.</p>
            </section>
        @else
            <section class="card chart-card">
                <div class="plan-tools">
                    <label class="field">Search
                        <input type="search" id="plan-q" placeholder="Epic, spec, task…" autocomplete="off">
                    </label>
                    <label class="field">Assignee
                        <select id="plan-assignee">
                            <option value="">Everyone</option>
                            @foreach ($assignees as $person)
                                <option value="{{ strtolower($person) }}">{{ $person }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">Status
                        <select id="plan-status">
                            <option value="">Any</option>
                            <option value="todo">To do</option>
                            <option value="planned">Planned</option>
                            <option value="progress">In progress</option>
                            <option value="review">Review</option>
                            <option value="done">Done</option>
                            <option value="risk">At risk</option>
                        </select>
                    </label>
                    @if ($taskCount > 0)
                        <label class="check">
                            <input type="checkbox" id="plan-tasks" checked>
                            Show tasks
                        </label>
                    @endif
                    <span class="plan-count" id="plan-count"></span>
                </div>

                <div class="chart-scroll">
                    <div class="chart" style="--track: {{ $trackMin }}px;">
                        <div class="gantt-head">
                            <div class="label-col">Schedule</div>
                            <div class="scale">
                                @foreach ($ticks as $tick)
                                    <span class="tick" style="left: {{ $tick['offset'] }}%;">{{ $tick['label'] }}</span>
                                @endforeach
                                @if ($todayOffset !== null)
                                    <span class="today-flag" style="left: {{ $todayOffset }}%;"><span>Today</span></span>
                                @endif
                            </div>
                        </div>

                        @if ($milestones !== [])
                            <div class="gantt-row is-lane" data-kind="lane">
                                <div class="label-col">Milestones</div>
                                <div class="track">
                                    @foreach ($ticks as $tick)
                                        <span class="gridline" style="left: {{ $tick['offset'] }}%;"></span>
                                    @endforeach
                                    @if ($todayOffset !== null)
                                        <span class="today-line" style="left: {{ $todayOffset }}%;"></span>
                                    @endif
                                    @foreach ($milestones as $milestone)
                                        <button type="button" @class(['diamond', $milestone['status'] ?? 'on_track']) style="left: {{ $offsetPct($milestone['date'] ?? null) }}%;" aria-label="{{ $milestone['label'] ?? 'Deadline' }}">
                                            <span class="tip">
                                                <strong>{{ $milestone['label'] ?? 'Deadline' }}</strong>
                                                {{ $pretty($milestone['date'] ?? null) }} · {{ $milestoneLabel((string) ($milestone['status'] ?? 'on_track')) }}
                                                @if (! empty($milestone['note']))
                                                    <br>{{ $milestone['note'] }}
                                                @endif
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @foreach ($groups as $group)
                            @php
                                $epic = $group['epic'];
                                $epicId = $group['id'];
                                $epicStart = $epic['start'] ?? null;
                                $epicEnd = $epic['forecast_end'] ?? null;
                                $childPeople = [];

                                foreach ($group['children'] as $child) {
                                    foreach ($child['assignees'] as $person) {
                                        $childPeople[] = strtolower($person);
                                    }
                                }

                                $childPeople = array_values(array_unique($childPeople));
                                $epicSearch = strtolower(implode(' ', array_filter([
                                    $epic['code'] ?? '',
                                    $epic['title'] ?? '',
                                    $epic['objective'] ?? '',
                                    $epic['deadline'] ?? '',
                                ])));
                            @endphp
                            <div
                                class="gantt-row is-epic"
                                data-kind="epic"
                                data-epic="{{ $epicId }}"
                                data-open="1"
                                data-search="{{ $epicSearch }}"
                                data-status="{{ $group['status_class'] }}"
                                data-assignee="{{ implode(',', $childPeople) }}"
                            >
                                <div class="label-col">
                                    @if ($group['children'] !== [])
                                        <button type="button" class="fold" aria-expanded="true" aria-label="Collapse {{ $epic['title'] ?? 'group' }}">▾</button>
                                    @endif
                                    <div class="name">
                                        <span class="title">
                                            @if ($group['loose'])
                                                No epic
                                            @else
                                                {{ $epic['code'] ?? '' }} — {{ $epic['title'] ?? '' }}
                                            @endif
                                        </span>
                                        <span class="meta">
                                            @if (! $group['loose'] && ! empty($epic['objective']))
                                                {{ $epic['objective'] }}
                                            @endif
                                            @if (! empty($epic['deadline']))
                                                · due {{ $pretty($epic['deadline']) }}
                                            @endif
                                            @if (! empty($epic['points']))
                                                · {{ $epic['points'] }} SP
                                            @endif
                                        </span>
                                    </div>
                                </div>
                                <div class="track">
                                    @foreach ($ticks as $tick)
                                        <span class="gridline" style="left: {{ $tick['offset'] }}%;"></span>
                                    @endforeach
                                    @if ($todayOffset !== null)
                                        <span class="today-line" style="left: {{ $todayOffset }}%;"></span>
                                    @endif
                                    @if ($epicStart && $epicEnd)
                                        <div @class(['bar', 'epic', $group['status_class']]) style="left: {{ $offsetPct($epicStart) }}%; width: {{ $widthPct($epicStart, $epicEnd) }}%;" tabindex="0">
                                            <span class="tip">
                                                <strong>{{ $group['loose'] ? 'No epic' : (($epic['code'] ?? '').' — '.($epic['title'] ?? '')) }}</strong>
                                                {{ $pretty($epicStart) }} → {{ $pretty($epicEnd) }}
                                                @if (! empty($epic['deadline']))
                                                    <br>Deadline {{ $pretty($epic['deadline']) }}
                                                @endif
                                                @if (! empty($epic['objective']))
                                                    <br>{{ $epic['objective'] }}
                                                @endif
                                            </span>
                                        </div>
                                    @endif
                                    @if (! empty($epic['deadline']))
                                        <span class="deadline" style="left: {{ $offsetPct($epic['deadline']) }}%;">
                                            <span class="tip"><strong>Epic deadline</strong>{{ $pretty($epic['deadline']) }}</span>
                                        </span>
                                    @endif
                                </div>
                            </div>

                            @foreach ($group['children'] as $child)
                                @php
                                    $specSearch = strtolower($child['label'].' '.$child['code']);
                                    $specPeople = array_map('strtolower', $child['assignees']);
                                @endphp
                                <div
                                    class="gantt-row is-spec"
                                    data-kind="spec"
                                    data-epic="{{ $epicId }}"
                                    data-spec="{{ $child['code'] }}"
                                    data-leaf="{{ $child['leaf'] ? '1' : '0' }}"
                                    data-search="{{ $specSearch }}"
                                    data-status="{{ $child['status_class'] }}"
                                    data-assignee="{{ implode(',', $specPeople) }}"
                                >
                                    <div class="label-col">
                                        <div class="name">
                                            <span class="title">{{ $child['label'] }}</span>
                                            <span class="meta">
                                                {{ $statusLabel($child['status']) }}
                                                · {{ $pretty($child['start']) }} → {{ $pretty($child['end']) }}
                                                @if ($child['hours'])
                                                    · {{ $child['hours'] }} h
                                                @endif
                                                @if ($specPeople !== [])
                                                    · {{ implode(', ', $child['assignees']) }}
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                    <div class="track">
                                        @foreach ($ticks as $tick)
                                            <span class="gridline" style="left: {{ $tick['offset'] }}%;"></span>
                                        @endforeach
                                        @if ($todayOffset !== null)
                                            <span class="today-line" style="left: {{ $todayOffset }}%;"></span>
                                        @endif
                                        @if ($child['start'] && $child['end'])
                                            <div @class(['bar', 'spec', $child['status_class']]) style="left: {{ $offsetPct($child['start']) }}%; width: {{ $widthPct($child['start'], $child['end']) }}%;" tabindex="0">
                                                <span class="fill" style="width: {{ round(((float) $child['progress']) * 100) }}%;"></span>
                                                <span class="tip">
                                                    <strong>{{ $child['label'] }}</strong>
                                                    {{ $statusLabel($child['status']) }} · {{ $pretty($child['start']) }} → {{ $pretty($child['end']) }}
                                                    @if ($child['points'])
                                                        <br>{{ $child['points'] }} SP
                                                    @endif
                                                    @if ($child['hours'])
                                                        <br>{{ $child['hours'] }} h estimated
                                                    @endif
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                @foreach ($child['tasks'] as $task)
                                    @php
                                        $taskStatus = $statusClass((string) ($task['status'] ?? ''));
                                        $taskSearch = strtolower(implode(' ', array_filter([
                                            $task['label'] ?? '',
                                            $task['task_id'] ?? '',
                                            $task['assignee'] ?? '',
                                            implode(' ', $task['depends_on'] ?? []),
                                        ])));
                                        $deps = is_array($task['depends_on'] ?? null) ? $task['depends_on'] : [];
                                    @endphp
                                    <div
                                        class="gantt-row is-task"
                                        data-kind="task"
                                        data-epic="{{ $epicId }}"
                                        data-spec="{{ $child['code'] }}"
                                        data-leaf="1"
                                        data-search="{{ $taskSearch }}"
                                        data-status="{{ $taskStatus }}"
                                        data-assignee="{{ strtolower((string) ($task['assignee'] ?? '')) }}"
                                    >
                                        <div class="label-col">
                                            <div class="name">
                                                <span class="title">{{ $task['label'] ?? '' }}</span>
                                                <span class="meta">
                                                    @if (! empty($task['assignee']))
                                                        {{ $task['assignee'] }}
                                                    @endif
                                                    @if (! empty($task['parallel']))
                                                        · parallel
                                                    @endif
                                                    @if ($deps !== [])
                                                        · after {{ implode(', ', $deps) }}
                                                    @endif
                                                    @if (! empty($task['estimate_hours']))
                                                        · {{ $task['estimate_hours'] }} h
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
                                        <div class="track">
                                            @foreach ($ticks as $tick)
                                                <span class="gridline" style="left: {{ $tick['offset'] }}%;"></span>
                                            @endforeach
                                            @if ($todayOffset !== null)
                                                <span class="today-line" style="left: {{ $todayOffset }}%;"></span>
                                            @endif
                                            <div @class(['bar', 'task', $taskStatus, 'parallel' => ! empty($task['parallel'])]) style="left: {{ $offsetPct($task['start'] ?? null) }}%; width: {{ $widthPct($task['start'] ?? null, $task['end'] ?? null) }}%;" tabindex="0">
                                                <span class="fill" style="width: {{ round(((float) ($task['progress'] ?? 0)) * 100) }}%;"></span>
                                                <span class="tip">
                                                    <strong>{{ $task['label'] ?? '' }}</strong>
                                                    {{ $statusLabel((string) ($task['status'] ?? '')) }} · {{ $pretty($task['start'] ?? null) }} → {{ $pretty($task['end'] ?? null) }}
                                                    @if (! empty($task['assignee']))
                                                        <br>{{ $task['assignee'] }}
                                                    @endif
                                                    @if (! empty($task['parallel']))
                                                        <br>Can run in parallel
                                                    @endif
                                                    @if ($deps !== [])
                                                        <br>After {{ implode(', ', $deps) }}
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @endforeach
                        @endforeach
                    </div>
                </div>

                <div class="legend" aria-label="Legend">
                    <span><i class="epic"></i> Epic window</span>
                    <span><i class="todo"></i> To do</span>
                    <span><i class="planned"></i> Planned</span>
                    <span><i class="progress"></i> In progress</span>
                    <span><i class="review"></i> Review</span>
                    <span><i class="done"></i> Done</span>
                    <span><i class="risk"></i> At risk</span>
                    <span><i class="parallel"></i> Parallel</span>
                    <span><i class="milestone"></i> Milestone</span>
                </div>
            </section>
        @endif

        @if ($epics !== [])
            <div class="epic-grid">
                @foreach ($epics as $epic)
                    @php
                        $slips = ! empty($epic['deadline']) && ! empty($epic['forecast_end']) && $epic['forecast_end'] > $epic['deadline'];
                    @endphp
                    <article class="card epic-card">
                        <div class="kicker">{{ $epic['code'] ?? '' }}</div>
                        <h3>{{ $epic['title'] ?? '' }}</h3>
                        @if (! empty($epic['objective']))
                            <p>{{ $epic['objective'] }}</p>
                        @endif
                        <dl>
                            <dt>Deadline</dt>
                            <dd>{{ $pretty($epic['deadline'] ?? null) }}</dd>
                            <dt>Forecast</dt>
                            <dd @class(['slip' => $slips])>{{ $pretty($epic['forecast_end'] ?? null) }}</dd>
                            <dt>Points</dt>
                            <dd>{{ $epic['points'] ?? 0 }} SP</dd>
                            <dt>Specs</dt>
                            <dd>{{ count($epic['spec_codes'] ?? []) }}</dd>
                        </dl>
                        @if (! empty($epic['spec_codes']))
                            <div class="chips">
                                @foreach ($epic['spec_codes'] as $code)
                                    <span class="chip">{{ $code }}</span>
                                @endforeach
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif

        @if ($notes !== [])
            <section class="card notes">
                <h3>Schedule notes</h3>
                <ul>
                    @foreach (array_reverse($notes) as $note)
                        <li>
                            <time>{{ $pretty(isset($note['ts']) ? substr((string) $note['ts'], 0, 10) : null) }}</time>
                            <span class="tag">{{ $milestoneLabel((string) ($note['status'] ?? 'on_track')) }}</span>
                            <span>{{ $note['message'] ?? '' }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endsection

@if (($gantt['bars'] ?? []) !== [] || ($gantt['milestones'] ?? []) !== [])
@push('scripts')
<script>
(() => {
    const rows = Array.from(document.querySelectorAll('.gantt-row[data-kind]'));
    const q = document.getElementById('plan-q');
    const assignee = document.getElementById('plan-assignee');
    const status = document.getElementById('plan-status');
    const tasksToggle = document.getElementById('plan-tasks');
    const count = document.getElementById('plan-count');

    const needle = () => (q && q.value ? q.value.trim().toLowerCase() : '');

    const passesAssignee = (row) => {
        if (!assignee || assignee.value === '') return true;
        return (row.dataset.assignee || '').split(',').filter(Boolean).includes(assignee.value);
    };

    const passesStatus = (row) => {
        if (!status || status.value === '') return true;
        return row.dataset.status === status.value;
    };

    const passesSearch = (row, term) => term === '' || (row.dataset.search || '').includes(term);

    const leavesOf = (row) => rows.filter((candidate) => {
        if (candidate.dataset.leaf !== '1') return false;
        if (row.dataset.kind === 'epic') return candidate.dataset.epic === row.dataset.epic;
        if (row.dataset.kind === 'spec') return candidate.dataset.epic === row.dataset.epic && candidate.dataset.spec === row.dataset.spec;
        return false;
    });

    const showAncestors = (visible, row) => {
        rows.forEach((candidate) => {
            if (candidate.dataset.kind === 'epic' && candidate.dataset.epic === row.dataset.epic) visible.add(candidate);
            if (candidate.dataset.kind === 'spec' && row.dataset.kind === 'task' && candidate.dataset.epic === row.dataset.epic && candidate.dataset.spec === row.dataset.spec) {
                visible.add(candidate);
            }
        });
    };

    const render = () => {
        const term = needle();
        const detail = !tasksToggle || tasksToggle.checked;
        const collapsed = new Set(rows.filter((row) => row.dataset.kind === 'epic' && row.dataset.open === '0').map((row) => row.dataset.epic));
        const visible = new Set();
        const leaves = rows.filter((row) => row.dataset.leaf === '1');

        leaves.forEach((row) => {
            if (!passesAssignee(row) || !passesStatus(row) || !passesSearch(row, term)) return;
            visible.add(row);
            showAncestors(visible, row);
        });

        const narrowing = term !== '' || (assignee && assignee.value !== '') || (status && status.value !== '');

        if (!narrowing) {
            rows.forEach((row) => {
                if (row.dataset.kind === 'epic') visible.add(row);
            });
        }

        rows.filter((row) => row.dataset.leaf !== '1' && row.dataset.kind !== 'lane').forEach((row) => {
            if (term === '' || !passesSearch(row, term)) return;
            const descendants = leavesOf(row).filter((leaf) => passesAssignee(leaf) && passesStatus(leaf));
            if (descendants.length === 0 && row.dataset.kind !== 'epic') return;
            visible.add(row);
            descendants.forEach((leaf) => visible.add(leaf));
            showAncestors(visible, row);
        });

        let shown = 0;
        rows.forEach((row) => {
            if (row.dataset.kind === 'lane') {
                row.hidden = false;
                return;
            }

            let show = visible.has(row);
            if (row.dataset.kind === 'task' && !detail) show = false;
            if ((row.dataset.kind === 'spec' || row.dataset.kind === 'task') && collapsed.has(row.dataset.epic)) show = false;
            row.hidden = !show;
            if (show) shown += 1;
        });

        if (count) count.textContent = shown + ' rows';
    };

    rows.filter((row) => row.dataset.kind === 'epic').forEach((row) => {
        const button = row.querySelector('.fold');
        if (!button) return;
        button.addEventListener('click', () => {
            const open = row.dataset.open !== '0';
            row.dataset.open = open ? '0' : '1';
            button.setAttribute('aria-expanded', open ? 'false' : 'true');
            button.textContent = open ? '▸' : '▾';
            render();
        });
    });

    [q, assignee, status, tasksToggle].forEach((el) => {
        if (!el) return;
        el.addEventListener('input', render);
        el.addEventListener('change', render);
    });

    render();
})();
</script>
@endpush
@endif
