@extends('larapilot::dashboard.layout')

@section('title', 'Plan')

@push('styles')
<style>
    body .shell:has(.plan-page) {
        max-width: none;
        padding-left: max(20px, 3vw);
        padding-right: max(20px, 3vw);
    }

    .plan-page { display: flex; flex-direction: column; gap: 18px; }

    .plan-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        flex-wrap: wrap;
    }

    .plan-top h2 { margin: 0 0 6px; font-size: 1.15rem; }

    .plan-top .sub {
        margin: 0;
        color: var(--muted);
        font-size: 0.875rem;
        max-width: 78ch;
        line-height: 1.5;
    }

    .health {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        border-radius: 999px;
        font-size: 0.82rem;
        font-weight: 650;
        border: 1px solid var(--border);
        background: var(--surface);
        white-space: nowrap;
    }

    .health .dot {
        width: 8px;
        height: 8px;
        border-radius: 999px;
        background: var(--status-done);
    }

    .health.at-risk { color: #b45309; border-color: color-mix(in srgb, #f59e0b 55%, var(--border)); }
    .health.at-risk .dot { background: #f59e0b; }
    .health.late { color: #b91c1c; border-color: color-mix(in srgb, #ef4444 55%, var(--border)); }
    .health.late .dot { background: #ef4444; }
    .health.ok { color: #047857; border-color: color-mix(in srgb, var(--status-done) 45%, var(--border)); }

    .metrics {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 14px;
    }

    .metric { padding: 16px 18px; }
    .metric-label {
        color: var(--muted);
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-weight: 600;
    }
    .metric-value {
        margin-top: 6px;
        font-size: 1.35rem;
        font-weight: 700;
        line-height: 1.15;
        font-variant-numeric: tabular-nums;
    }
    .metric-note {
        margin-top: 6px;
        color: var(--muted);
        font-size: 0.75rem;
    }

    .alerts { display: grid; gap: 8px; }
    .alert {
        padding: 12px 14px;
        border-radius: 10px;
        border: 1px solid var(--border);
        font-size: 0.85rem;
        line-height: 1.45;
    }
    .alert strong { font-weight: 650; }
    .alert.critical { border-color: #ef4444; background: color-mix(in srgb, #ef4444 8%, var(--surface)); }
    .alert.warning { border-color: #f59e0b; background: color-mix(in srgb, #f59e0b 10%, var(--surface)); }
    .alert .when { color: var(--muted); font-variant-numeric: tabular-nums; }

    .milestones {
        display: flex;
        gap: 10px;
        overflow-x: auto;
        padding-bottom: 2px;
    }

    .milestone {
        flex: 0 0 auto;
        min-width: 180px;
        max-width: 260px;
        padding: 12px 14px;
        border-radius: 12px;
        border: 1px solid var(--border);
        background: var(--surface);
        box-shadow: var(--shadow);
    }

    .milestone .date {
        font-size: 0.75rem;
        font-weight: 650;
        letter-spacing: 0.02em;
        color: var(--muted);
        font-variant-numeric: tabular-nums;
    }

    .milestone strong {
        display: block;
        margin-top: 4px;
        font-size: 0.92rem;
    }

    .milestone .state {
        display: inline-block;
        margin-top: 8px;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .milestone.on_track .state { color: #047857; }
    .milestone.at_risk .state { color: #b45309; }
    .milestone.delayed .state { color: #b91c1c; }
    .milestone.done .state { color: var(--muted); }
    .milestone .note {
        margin: 6px 0 0;
        color: var(--muted);
        font-size: 0.78rem;
        line-height: 1.4;
    }

    .chart-card { padding: 16px 16px 12px; }

    .plan-tools {
        display: flex;
        flex-wrap: wrap;
        gap: 10px 12px;
        align-items: end;
        margin-bottom: 14px;
    }

    .plan-tools label {
        display: grid;
        gap: 4px;
        font-size: 0.72rem;
        color: var(--muted);
        font-weight: 650;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .plan-tools input[type="search"],
    .plan-tools select {
        padding: 7px 10px;
        border-radius: 8px;
        border: 1px solid var(--border);
        background: var(--bg);
        color: var(--text);
        font-size: 0.85rem;
        font-weight: 400;
        text-transform: none;
        letter-spacing: normal;
        min-width: 160px;
    }

    .plan-tools .check {
        display: flex;
        align-items: center;
        gap: 8px;
        text-transform: none;
        letter-spacing: normal;
        font-size: 0.85rem;
        font-weight: 550;
        color: var(--text);
        padding-bottom: 8px;
    }

    .plan-count {
        margin-left: auto;
        padding-bottom: 8px;
        color: var(--muted);
        font-size: 0.78rem;
        font-variant-numeric: tabular-nums;
    }

    .chart-scroll {
        --label: 292px;
        overflow-x: auto;
        border: 1px solid var(--border);
        border-radius: 10px;
        background:
            linear-gradient(var(--surface), var(--surface)) 0 0 / var(--label) 100% no-repeat,
            var(--bg);
    }

    .chart {
        --label: 292px;
        --row: 36px;
        min-width: calc(var(--label) + var(--track));
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
        font-size: 0.72rem;
        font-weight: 650;
        letter-spacing: 0.04em;
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
        font-size: 0.7rem;
        color: var(--muted);
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
        border-left: 2px solid #ef4444;
    }

    .today-flag span {
        position: absolute;
        top: 4px;
        left: 4px;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #ef4444;
        background: var(--surface);
        padding: 0 4px;
        border-radius: 4px;
    }

    .gantt-row { border-bottom: 1px solid color-mix(in srgb, var(--border) 70%, transparent); }
    .gantt-row:last-child { border-bottom: 0; }
    .gantt-row.is-epic { background: color-mix(in srgb, var(--accent) 4%, transparent); }
    .gantt-row.is-epic .label-col { background: color-mix(in srgb, var(--accent) 4%, var(--surface)); font-weight: 700; }
    .gantt-row.is-spec .label-col { padding-left: 28px; }
    .gantt-row.is-task .label-col { padding-left: 46px; }
    .gantt-row.is-lane .label-col { color: var(--muted); font-size: 0.75rem; font-weight: 650; letter-spacing: 0.04em; text-transform: uppercase; }

    .fold {
        flex: 0 0 auto;
        width: 22px;
        height: 22px;
        border-radius: 6px;
        border: 1px solid var(--border);
        background: var(--bg);
        color: var(--text);
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

    .today-line { width: 2px; background: #ef4444; z-index: 1; }

    .bar {
        position: absolute;
        top: 8px;
        height: 20px;
        border-radius: 5px;
        min-width: 8px;
        background: color-mix(in srgb, var(--status-todo) 70%, transparent);
        z-index: 1;
    }

    .bar.todo { background: color-mix(in srgb, var(--status-todo) 75%, transparent); }
    .bar.planned { background: color-mix(in srgb, var(--status-planned) 78%, transparent); }
    .bar.progress { background: color-mix(in srgb, var(--status-progress) 80%, var(--accent)); }
    .bar.review { background: color-mix(in srgb, var(--status-review) 78%, transparent); }
    .bar.done { background: color-mix(in srgb, var(--status-done) 78%, transparent); }
    .bar.risk { background: color-mix(in srgb, #f59e0b 75%, transparent); }

    .bar.epic {
        top: 12px;
        height: 12px;
        border-radius: 2px 2px 0 0;
        background: transparent;
        border-top: 3px solid var(--accent);
        min-width: 10px;
    }

    .bar.epic::before,
    .bar.epic::after {
        content: '';
        position: absolute;
        top: -3px;
        width: 2px;
        height: 12px;
        background: var(--accent);
    }

    .bar.epic::before { left: 0; }
    .bar.epic::after { right: 0; }
    .bar.epic.risk { border-top-color: #d97706; }
    .bar.epic.risk::before,
    .bar.epic.risk::after { background: #d97706; }
    .bar.epic.done { border-top-color: var(--status-done); }
    .bar.epic.done::before,
    .bar.epic.done::after { background: var(--status-done); }

    .bar.task { top: 11px; height: 14px; }
    .bar.parallel { box-shadow: inset 0 0 0 1.5px color-mix(in srgb, #0ea5e9 85%, white); }

    .bar .fill {
        position: absolute;
        inset: 0 auto 0 0;
        background: rgba(255, 255, 255, 0.28);
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
        background: #d97706;
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
    .diamond.at_risk { background: #f59e0b; }
    .diamond.delayed { background: #ef4444; }
    .diamond.done { background: var(--status-todo); }

    .tip {
        position: absolute;
        left: 0;
        bottom: calc(100% + 8px);
        width: max-content;
        max-width: 280px;
        padding: 8px 10px;
        border-radius: 8px;
        background: var(--text);
        color: var(--bg);
        font-size: 0.75rem;
        line-height: 1.4;
        font-weight: 450;
        white-space: normal;
        box-shadow: var(--shadow);
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

    .tip strong { display: block; font-weight: 650; margin-bottom: 2px; }

    .legend {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 16px;
        margin-top: 12px;
        color: var(--muted);
        font-size: 0.75rem;
    }

    .legend i {
        display: inline-block;
        width: 16px;
        height: 8px;
        margin-right: 6px;
        border-radius: 2px;
        vertical-align: middle;
    }

    .legend i.todo { background: color-mix(in srgb, var(--status-todo) 75%, transparent); }
    .legend i.planned { background: color-mix(in srgb, var(--status-planned) 78%, transparent); }
    .legend i.progress { background: color-mix(in srgb, var(--status-progress) 80%, var(--accent)); }
    .legend i.review { background: color-mix(in srgb, var(--status-review) 78%, transparent); }
    .legend i.done { background: color-mix(in srgb, var(--status-done) 78%, transparent); }
    .legend i.risk { background: color-mix(in srgb, #f59e0b 75%, transparent); }
    .legend i.epic {
        background: transparent;
        border-top: 3px solid var(--accent);
        height: 8px;
        position: relative;
    }
    .legend i.parallel { box-shadow: inset 0 0 0 1.5px #0ea5e9; background: transparent; }
    .legend i.milestone {
        width: 8px;
        height: 8px;
        transform: rotate(45deg);
        background: var(--status-planned);
        border-radius: 1px;
    }

    .epic-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 12px;
    }

    .epic-card { padding: 14px 16px; }
    .epic-card .kicker {
        color: var(--muted);
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }
    .epic-card h3 { margin: 4px 0 6px; font-size: 1rem; }
    .epic-card p { margin: 0; color: var(--muted); font-size: 0.84rem; line-height: 1.45; }
    .epic-card dl {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 4px 12px;
        margin: 12px 0 0;
        font-size: 0.8rem;
    }
    .epic-card dt { color: var(--muted); }
    .epic-card dd { margin: 0; font-variant-numeric: tabular-nums; }
    .epic-card .slip { color: #b45309; font-weight: 650; }
    .chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }
    .chip {
        padding: 2px 8px;
        border-radius: 999px;
        border: 1px solid var(--border);
        font-size: 0.72rem;
        color: var(--muted);
    }

    .notes { padding: 16px 18px; }
    .notes h3 { margin: 0 0 12px; font-size: 0.95rem; }
    .notes ul { margin: 0; padding: 0; list-style: none; display: grid; gap: 8px; }
    .notes li {
        display: grid;
        grid-template-columns: 148px 88px 1fr;
        gap: 10px;
        font-size: 0.82rem;
        padding-bottom: 8px;
        border-bottom: 1px solid var(--border);
    }
    .notes li:last-child { border-bottom: 0; padding-bottom: 0; }
    .notes time, .notes .tag { color: var(--muted); font-variant-numeric: tabular-nums; }

    .empty-plan { padding: 36px 24px; text-align: center; }
    .empty-plan h3 { margin: 0 0 8px; }
    .empty-plan p { margin: 0 auto; max-width: 62ch; color: var(--muted); }

    @media (max-width: 720px) {
        .notes li { grid-template-columns: 1fr; gap: 2px; }
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
        <div class="plan-top">
            <div>
                <h2>Plan</h2>
                <p class="sub">Epics, deadlines, and a dependency-aware Gantt from specs and plans. Token spend stays on <a href="{{ route('larapilot.dashboard.usage') }}">Usage</a>.</p>
            </div>
            <div @class(['health', $healthClass])>
                <span class="dot" aria-hidden="true"></span>
                {{ $healthLabel }}
            </div>
        </div>

        <div class="metrics">
            <div class="card metric">
                <div class="metric-label">Window</div>
                <div class="metric-value" style="font-size:1.05rem;">{{ $pretty($start) }}</div>
                <div class="metric-note">through {{ $pretty($end) }} · {{ $span }} days</div>
            </div>
            <div class="card metric">
                <div class="metric-label">Remaining</div>
                <div class="metric-value">{{ $criticality['remaining_points'] ?? 0 }} <span style="font-size:0.85rem;font-weight:600;color:var(--muted);">SP</span></div>
                <div class="metric-note">~{{ $criticality['remaining_hours'] ?? 0 }} h · ~{{ $criticality['forecast_work_days'] ?? 0 }} work-days</div>
            </div>
            <div class="card metric">
                <div class="metric-label">Forecast end</div>
                <div class="metric-value" style="font-size:1.05rem;">{{ $pretty($criticality['forecast_end'] ?? null) }}</div>
                <div class="metric-note">from remaining effort, not the calendar window</div>
            </div>
            <div class="card metric">
                <div class="metric-label">Scope</div>
                <div class="metric-value">{{ count($epics) }} <span style="font-size:0.85rem;font-weight:600;color:var(--muted);">epics</span></div>
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
                    <article @class(['milestone', $milestone['status'] ?? 'on_track'])>
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
                    <label>Search
                        <input type="search" id="plan-q" placeholder="Epic, spec, task…" autocomplete="off">
                    </label>
                    <label>Assignee
                        <select id="plan-assignee">
                            <option value="">Everyone</option>
                            @foreach ($assignees as $person)
                                <option value="{{ strtolower($person) }}">{{ $person }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Status
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
