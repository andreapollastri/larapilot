@extends('larapilot::dashboard.layout')

@section('title', 'Usage')

@push('styles')
<style>
    .usage-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }

    .usage-top h2 {
        margin: 0;
        font-size: 1.15rem;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        padding: 8px 14px;
        border-radius: 999px;
        border: 1px solid var(--accent);
        background: var(--accent-soft);
        color: var(--accent);
        font-size: 0.875rem;
        font-weight: 600;
        text-decoration: none;
    }

    .btn:hover { text-decoration: none; }

    .metrics {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 14px;
        margin-bottom: 22px;
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
        font-size: 1.6rem;
        font-weight: 700;
        line-height: 1;
    }

    .panel {
        padding: 18px 20px;
        margin-bottom: 20px;
    }

    .panel h3 {
        margin: 0 0 14px;
        font-size: 0.95rem;
    }

    .panel .hint {
        margin: -6px 0 14px;
        color: var(--muted);
        font-size: 0.8rem;
        line-height: 1.45;
    }

    .bars {
        display: grid;
        gap: 10px;
    }

    .bar-row {
        display: grid;
        grid-template-columns: 140px 1fr 70px;
        gap: 10px;
        align-items: center;
        font-size: 0.85rem;
    }

    .bar-track {
        height: 10px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--border) 70%, transparent);
        overflow: hidden;
    }

    .bar-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--accent), #0ea5e9);
        border-radius: 999px;
    }

    .gantt {
        overflow-x: auto;
        padding-bottom: 8px;
    }

    .gantt-row {
        display: grid;
        grid-template-columns: 260px 1fr;
        gap: 12px;
        align-items: center;
        margin-bottom: 10px;
        min-width: 720px;
        font-size: 0.8rem;
    }

    .gantt-row.is-epic .gantt-label { font-weight: 700; color: var(--text); }
    .gantt-row.is-task .gantt-label { padding-left: 10px; }

    .gantt-label {
        color: var(--muted);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .gantt-meta {
        display: block;
        font-size: 0.7rem;
        color: var(--muted);
        opacity: 0.9;
    }

    .gantt-track {
        position: relative;
        height: 22px;
        border-radius: 6px;
        background: color-mix(in srgb, var(--border) 55%, transparent);
    }

    .gantt-bar {
        position: absolute;
        top: 3px;
        bottom: 3px;
        border-radius: 4px;
        background: color-mix(in srgb, var(--status-progress) 55%, var(--accent));
        min-width: 8px;
    }

    .gantt-bar.done { background: color-mix(in srgb, var(--status-done) 70%, transparent); }
    .gantt-bar.review { background: color-mix(in srgb, var(--status-review) 70%, transparent); }
    .gantt-bar.todo { background: color-mix(in srgb, var(--status-todo) 70%, transparent); }
    .gantt-bar.planned { background: color-mix(in srgb, var(--status-planned) 70%, transparent); }
    .gantt-bar.progress { background: color-mix(in srgb, var(--status-progress) 70%, var(--accent)); }
    .gantt-bar.epic {
        background: color-mix(in srgb, var(--accent) 35%, transparent);
        border: 1px dashed var(--accent);
        top: 1px;
        bottom: 1px;
    }
    .gantt-bar.parallel::after {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: 4px;
        box-shadow: inset 0 0 0 1px color-mix(in srgb, #0ea5e9 70%, transparent);
    }

    .gantt-progress {
        position: absolute;
        inset: 0 auto 0 0;
        background: rgba(255,255,255,0.25);
        border-radius: 4px;
    }

    .gantt-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 10px 16px;
        margin-top: 16px;
        padding-top: 14px;
        border-top: 1px solid var(--border);
        font-size: 0.78rem;
        color: var(--muted);
    }

    .legend-item {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .legend-swatch {
        width: 18px;
        height: 10px;
        border-radius: 3px;
        background: color-mix(in srgb, var(--status-progress) 55%, var(--accent));
    }

    .legend-swatch.done { background: color-mix(in srgb, var(--status-done) 70%, transparent); }
    .legend-swatch.review { background: color-mix(in srgb, var(--status-review) 70%, transparent); }
    .legend-swatch.todo { background: color-mix(in srgb, var(--status-todo) 70%, transparent); }
    .legend-swatch.planned { background: color-mix(in srgb, var(--status-planned) 70%, transparent); }
    .legend-swatch.progress { background: color-mix(in srgb, var(--status-progress) 70%, var(--accent)); }
    .legend-swatch.epic {
        background: color-mix(in srgb, var(--accent) 35%, transparent);
        border: 1px dashed var(--accent);
    }
    .legend-swatch.parallel {
        box-shadow: inset 0 0 0 1px color-mix(in srgb, #0ea5e9 70%, transparent);
    }
    .legend-swatch.milestone {
        width: 8px;
        background: color-mix(in srgb, var(--status-done) 70%, transparent);
    }

    .milestone {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 10px;
        border-radius: 8px;
        border: 1px solid var(--border);
        margin: 0 8px 8px 0;
        font-size: 0.8rem;
    }

    .milestone.at_risk { border-color: #f59e0b; color: #d97706; }
    .milestone.delayed { border-color: #ef4444; color: #dc2626; }
    .milestone.done { border-color: #10b981; color: #059669; }

    .alert-list { display: grid; gap: 8px; }
    .alert {
        padding: 10px 12px;
        border-radius: 8px;
        border: 1px solid var(--border);
        font-size: 0.85rem;
    }
    .alert.critical { border-color: #ef4444; background: color-mix(in srgb, #ef4444 8%, transparent); }
    .alert.warning { border-color: #f59e0b; background: color-mix(in srgb, #f59e0b 8%, transparent); }

    .epic-grid {
        display: grid;
        gap: 10px;
    }
    .epic-card {
        padding: 12px 14px;
        border: 1px solid var(--border);
        border-radius: 10px;
        font-size: 0.85rem;
    }
    .epic-card strong { display: block; margin-bottom: 4px; }
    .epic-card .muted { color: var(--muted); font-size: 0.8rem; }

    .filters {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 10px;
        margin-bottom: 14px;
    }

    .filters label {
        display: grid;
        gap: 4px;
        font-size: 0.75rem;
        color: var(--muted);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .filters input, .filters select {
        padding: 7px 10px;
        border-radius: 8px;
        border: 1px solid var(--border);
        background: var(--bg);
        color: var(--text);
        font-size: 0.85rem;
        font-weight: 400;
        text-transform: none;
        letter-spacing: normal;
    }

    .entries {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
    }

    .entries th, .entries td {
        text-align: left;
        padding: 8px 10px;
        border-bottom: 1px solid var(--border);
        vertical-align: top;
    }

    .entries th { color: var(--muted); font-weight: 600; }

    .pager {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-top: 12px;
        font-size: 0.8rem;
        color: var(--muted);
    }

    .pager button {
        padding: 6px 12px;
        border-radius: 8px;
        border: 1px solid var(--border);
        background: color-mix(in srgb, var(--border) 35%, transparent);
        color: var(--text);
        cursor: pointer;
        font-size: 0.8rem;
    }

    .pager button:disabled {
        opacity: 0.45;
        cursor: default;
    }

    .reason-list {
        margin: 0;
        padding-left: 18px;
        font-size: 0.82rem;
        color: var(--muted);
        line-height: 1.5;
    }
</style>
@endpush

@section('content')
    @php
        $summary = $summary ?? [];
        $byCategory = $summary['by_category'] ?? [];
        $tokenValues = array_map(fn ($r) => (int) ($r['tokens'] ?? 0), $byCategory);
        $maxTokens = max(1, $tokenValues === [] ? 1 : max($tokenValues));
        $gantt = $gantt ?? ['bars' => [], 'milestones' => [], 'project_start' => null, 'project_end' => null, 'epics' => [], 'legend' => []];
        $zoey = $zoey ?? [];
        $criticality = $criticality ?? ['alerts' => [], 'on_track' => true];
        $start = $gantt['project_start'] ?? null;
        $end = $gantt['project_end'] ?? null;
        $span = 1;
        if ($start && $end) {
            $span = max(1, (new \DateTimeImmutable($end))->diff(new \DateTimeImmutable($start))->days + 1);
        }
        $formatTokens = function (int $tokens): string {
            if ($tokens < 1000) {
                return (string) $tokens;
            }
            $k = $tokens / 1000;
            if (abs($k - round($k)) < 0.05) {
                return ((int) round($k)).'K';
            }

            return rtrim(rtrim(number_format($k, 1, '.', ''), '0'), '.').'K';
        };
        $hoursFromMinutes = function (float|int $minutes): string {
            $hours = round(((float) $minutes) / 60, 2);

            return rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.') ?: '0';
        };
        $statusClass = function (string $status): string {
            $s = strtolower($status);
            return match (true) {
                str_contains($s, 'done') => 'done',
                str_contains($s, 'review') => 'review',
                str_contains($s, 'progress') => 'progress',
                str_contains($s, 'risk') => 'review',
                str_contains($s, 'planned') => 'planned',
                default => 'todo',
            };
        };
        $offsetPct = function (?string $date) use ($start, $span): float {
            if (! $date || ! $start) {
                return 0;
            }
            $days = (new \DateTimeImmutable($date))->diff(new \DateTimeImmutable($start))->days;
            return min(100, max(0, ($days / $span) * 100));
        };
        $widthPct = function (?string $from, ?string $to) use ($span): float {
            if (! $from || ! $to) {
                return 4;
            }
            $days = max(1, (new \DateTimeImmutable($to))->diff(new \DateTimeImmutable($from))->days + 1);
            return min(100, max(2, ($days / $span) * 100));
        };
    @endphp

    <div class="usage-top">
        <div>
            <h2>Lucille · Project tracking</h2>
            <p style="margin:4px 0 0;color:var(--muted);font-size:0.875rem;">Tokens, hours, deadlines, epics, and a dependency-aware project Gantt.</p>
        </div>
        <a class="btn" href="{{ route('larapilot.dashboard.usage.report') }}">Download report.md</a>
    </div>

    <div class="metrics">
        <div class="card metric">
            <div class="metric-label">Entries</div>
            <div class="metric-value">{{ $summary['entry_count'] ?? 0 }}</div>
        </div>
        <div class="card metric">
            <div class="metric-label">Tokens</div>
            <div class="metric-value">{{ $formatTokens((int) ($summary['total_tokens'] ?? 0)) }}</div>
        </div>
        <div class="card metric">
            <div class="metric-label">Hours</div>
            <div class="metric-value">{{ $summary['total_hours'] ?? 0 }}</div>
        </div>
        <div class="card metric">
            <div class="metric-label">Forecast end</div>
            <div class="metric-value" style="font-size:1.1rem;">{{ $criticality['forecast_end'] ?? '—' }}</div>
        </div>
    </div>

    <section class="card panel">
        <h3>Zoey vs Lucille</h3>
        <p class="hint">
            Ledger {{ $zoey['ledger_tokens_display'] ?? '0' }}
            · estimated {{ $zoey['estimated_tokens_display'] ?? '0' }}
            · measured {{ $zoey['measured_tokens_display'] ?? '0' }}
            @if (($zoey['estimated_entry_count'] ?? 0) > 0)
                · {{ $zoey['estimated_entry_count'] }}/{{ $zoey['entry_count'] ?? 0 }} entries marked estimated
            @endif
        </p>
        <ul class="reason-list">
            @foreach (($zoey['why_they_differ'] ?? []) as $reason)
                <li>{{ $reason }}</li>
            @endforeach
        </ul>
    </section>

    <section class="card panel">
        <h3>Schedule criticality</h3>
        @if (($criticality['on_track'] ?? true) && ($criticality['alerts'] ?? []) === [])
            <p class="hint" style="margin:0;">
                On track · remaining {{ $criticality['remaining_points'] ?? 0 }} SP
                · ~{{ $criticality['remaining_hours'] ?? 0 }} h
                · ~{{ $criticality['forecast_work_days'] ?? 0 }} work-days
            </p>
        @else
            <p class="hint">
                Remaining {{ $criticality['remaining_points'] ?? 0 }} SP · ~{{ $criticality['remaining_hours'] ?? 0 }} h
                · forecast {{ $criticality['forecast_end'] ?? '—' }}
            </p>
            <div class="alert-list">
                @foreach ($criticality['alerts'] as $alert)
                    <div @class(['alert', $alert['level'] ?? 'warning'])>
                        <strong>{{ $alert['label'] ?? 'Alert' }}</strong>
                        @if (!empty($alert['date']))
                            <span style="color:var(--muted);"> · {{ $alert['date'] }}</span>
                        @endif
                        <div>{{ $alert['message'] ?? '' }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="card panel">
        <h3>By category</h3>
        @if (($summary['entry_count'] ?? 0) === 0)
            <div class="empty" style="padding:20px;">No ledger entries yet. Agents log with <code>larapilot:usage-log</code>.</div>
        @else
            <div class="bars">
                @foreach ($byCategory as $category => $row)
                    @if (($row['entries'] ?? 0) > 0)
                        <div class="bar-row">
                            <div>{{ $category }}</div>
                            <div class="bar-track">
                                <div class="bar-fill" style="width: {{ round(((int) $row['tokens'] / $maxTokens) * 100) }}%"></div>
                            </div>
                            <div>{{ $formatTokens((int) $row['tokens']) }}</div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </section>

    <section class="card panel">
        <h3>Epics</h3>
        @if (($gantt['epics'] ?? []) === [])
            <div class="empty" style="padding:16px;">No epics yet. Specs should carry <code>epic: { code, title, objective, deadline }</code>.</div>
        @else
            <div class="epic-grid">
                @foreach ($gantt['epics'] as $epic)
                    <div class="epic-card">
                        <strong>{{ $epic['code'] ?? '' }} — {{ $epic['title'] ?? '' }}</strong>
                        <div class="muted">
                            @if (!empty($epic['objective']))
                                Objective: {{ $epic['objective'] }} ·
                            @endif
                            @if (!empty($epic['deadline']))
                                Deadline: {{ $epic['deadline'] }} ·
                            @endif
                            Forecast: {{ $epic['forecast_end'] ?? '—' }} ·
                            {{ $epic['points'] ?? 0 }} SP ·
                            {{ count($epic['spec_codes'] ?? []) }} specs
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="card panel">
        <h3>Milestones</h3>
        @if (($gantt['milestones'] ?? []) === [])
            <div class="empty" style="padding:16px;">No deadlines. Lucille records them with <code>larapilot:schedule-set</code>.</div>
        @else
            <div>
                @foreach ($gantt['milestones'] as $milestone)
                    <span @class(['milestone', $milestone['status'] ?? 'on_track'])>
                        <strong>{{ $milestone['label'] ?? 'Deadline' }}</strong>
                        <span>{{ $milestone['date'] ?? '' }}</span>
                        <span>{{ $milestone['status'] ?? '' }}</span>
                    </span>
                @endforeach
            </div>
        @endif
    </section>

    <section class="card panel">
        <h3>Project Gantt</h3>
        @if (($gantt['bars'] ?? []) === [] && ($gantt['milestones'] ?? []) === [])
            <div class="empty" style="padding:20px;">Gantt appears when specs or deadlines exist.</div>
        @else
            <p class="hint">
                Window: {{ $start ?? '—' }} → {{ $end ?? '—' }} · bars respect task <code>dependencies</code>, parallel work, and optional <code>assignee</code>
            </p>
            <div class="gantt">
                @foreach ($gantt['bars'] as $bar)
                    @php
                        $type = (string) ($bar['type'] ?? 'spec');
                        $barClasses = [$statusClass((string) ($bar['status'] ?? ''))];
                        if ($type === 'epic') {
                            $barClasses[] = 'epic';
                        }
                        if (!empty($bar['parallel'])) {
                            $barClasses[] = 'parallel';
                        }
                    @endphp
                    <div @class(['gantt-row', 'is-epic' => $type === 'epic', 'is-task' => $type === 'task'])>
                        <div class="gantt-label" title="{{ $bar['label'] ?? '' }}">
                            {{ $bar['label'] ?? '' }}
                            @if (!empty($bar['assignee']) || !empty($bar['depends_on']) || !empty($bar['parallel']))
                                <span class="gantt-meta">
                                    @if (!empty($bar['assignee']))
                                        {{ $bar['assignee'] }}
                                    @endif
                                    @if (!empty($bar['parallel']))
                                        · parallel
                                    @endif
                                    @if (!empty($bar['depends_on']))
                                        · after {{ implode(', ', $bar['depends_on']) }}
                                    @endif
                                </span>
                            @elseif ($type === 'epic' && (!empty($bar['objective']) || !empty($bar['deadline'])))
                                <span class="gantt-meta">
                                    @if (!empty($bar['objective'])) {{ $bar['objective'] }} @endif
                                    @if (!empty($bar['deadline'])) · due {{ $bar['deadline'] }} @endif
                                </span>
                            @endif
                        </div>
                        <div class="gantt-track">
                            <div @class(array_merge(['gantt-bar'], $barClasses))
                                 style="left: {{ $offsetPct($bar['start'] ?? null) }}%; width: {{ $widthPct($bar['start'] ?? null, $bar['end'] ?? null) }}%;">
                                <div class="gantt-progress" style="width: {{ round(((float) ($bar['progress'] ?? 0)) * 100) }}%"></div>
                            </div>
                        </div>
                    </div>
                @endforeach
                @foreach ($gantt['milestones'] as $milestone)
                    <div class="gantt-row">
                        <div class="gantt-label">◆ {{ $milestone['label'] ?? 'Deadline' }}</div>
                        <div class="gantt-track">
                            <div class="gantt-bar done" style="left: {{ $offsetPct($milestone['date'] ?? null) }}%; width: 2%;"></div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="gantt-legend">
                <span class="legend-item"><span class="legend-swatch epic"></span> Epic</span>
                <span class="legend-item"><span class="legend-swatch todo"></span> TODO</span>
                <span class="legend-item"><span class="legend-swatch planned"></span> PLANNED</span>
                <span class="legend-item"><span class="legend-swatch progress"></span> IN PROGRESS</span>
                <span class="legend-item"><span class="legend-swatch review"></span> REVIEW</span>
                <span class="legend-item"><span class="legend-swatch done"></span> DONE</span>
                <span class="legend-item"><span class="legend-swatch parallel"></span> Parallelizable</span>
                <span class="legend-item"><span class="legend-swatch milestone"></span> Milestone</span>
            </div>
        @endif
    </section>

    <section class="card panel" id="ledger-panel">
        <h3>Ledger history</h3>
        @if (($entries ?? []) === [])
            <div class="empty" style="padding:16px;">Empty ledger.</div>
        @else
            <div class="filters">
                <label>Search
                    <input type="search" id="ledger-q" placeholder="skill, spec, note…" autocomplete="off">
                </label>
                <label>Executor
                    <select id="ledger-user">
                        <option value="">All</option>
                        @foreach (($entry_users ?? []) as $user)
                            <option value="{{ $user }}">{{ $user }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Category
                    <select id="ledger-category">
                        <option value="">All</option>
                        @foreach (($entry_categories ?? []) as $category)
                            <option value="{{ $category }}">{{ $category }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Estimate
                    <select id="ledger-estimated">
                        <option value="">All</option>
                        <option value="1">Estimated</option>
                        <option value="0">Measured</option>
                    </select>
                </label>
            </div>
            <table class="entries" id="ledger-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Category</th>
                        <th>User</th>
                        <th>Tokens</th>
                        <th>Hours</th>
                        <th>Skill / Spec</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        @php
                            $searchBlob = strtolower(implode(' ', array_filter([
                                $entry['ts'] ?? '',
                                $entry['category'] ?? '',
                                $entry['user'] ?? '',
                                $entry['skill'] ?? '',
                                $entry['spec'] ?? '',
                                $entry['note'] ?? '',
                            ])));
                        @endphp
                        <tr
                            data-user="{{ $entry['user'] ?? '' }}"
                            data-category="{{ $entry['category'] ?? '' }}"
                            data-estimated="{{ !empty($entry['estimated']) ? '1' : '0' }}"
                            data-search="{{ $searchBlob }}"
                        >
                            <td>{{ $entry['ts'] ?? '' }}</td>
                            <td>{{ $entry['category'] ?? '' }}</td>
                            <td>{{ $entry['user'] ?? '' }}</td>
                            <td>{{ $formatTokens((int) ($entry['tokens'] ?? 0)) }}</td>
                            <td>{{ $hoursFromMinutes($entry['minutes'] ?? 0) }}</td>
                            <td>
                                {{ $entry['skill'] ?? '—' }}
                                @if (!empty($entry['spec']))
                                    · {{ $entry['spec'] }}
                                @endif
                                @if (!empty($entry['estimated']))
                                    · est.
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="pager">
                <span id="ledger-count">Showing 0</span>
                <div>
                    <button type="button" id="ledger-prev">Prev</button>
                    <button type="button" id="ledger-next">Next</button>
                </div>
            </div>
        @endif
    </section>
@endsection

@if (($entries ?? []) !== [])
@push('scripts')
<script>
(() => {
    const pageSize = 25;
    const rows = Array.from(document.querySelectorAll('#ledger-table tbody tr'));
    const q = document.getElementById('ledger-q');
    const user = document.getElementById('ledger-user');
    const category = document.getElementById('ledger-category');
    const estimated = document.getElementById('ledger-estimated');
    const prev = document.getElementById('ledger-prev');
    const next = document.getElementById('ledger-next');
    const count = document.getElementById('ledger-count');
    let page = 0;

    const filtered = () => {
        const needle = (q.value || '').trim().toLowerCase();
        const u = user.value;
        const c = category.value;
        const e = estimated.value;

        return rows.filter((row) => {
            if (u && row.dataset.user !== u) return false;
            if (c && row.dataset.category !== c) return false;
            if (e !== '' && row.dataset.estimated !== e) return false;
            if (needle && !(row.dataset.search || '').includes(needle)) return false;
            return true;
        });
    };

    const render = () => {
        const list = filtered();
        const pages = Math.max(1, Math.ceil(list.length / pageSize));
        if (page >= pages) page = pages - 1;
        if (page < 0) page = 0;

        rows.forEach((row) => { row.style.display = 'none'; });
        const start = page * pageSize;
        list.slice(start, start + pageSize).forEach((row) => { row.style.display = ''; });

        const from = list.length === 0 ? 0 : start + 1;
        const to = Math.min(list.length, start + pageSize);
        count.textContent = `Showing ${from}–${to} of ${list.length}`;
        prev.disabled = page <= 0;
        next.disabled = page >= pages - 1 || list.length === 0;
    };

    [q, user, category, estimated].forEach((el) => {
        el.addEventListener('input', () => { page = 0; render(); });
        el.addEventListener('change', () => { page = 0; render(); });
    });
    prev.addEventListener('click', () => { page -= 1; render(); });
    next.addEventListener('click', () => { page += 1; render(); });
    render();
})();
</script>
@endpush
@endif
