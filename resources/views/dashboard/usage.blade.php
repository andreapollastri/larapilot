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
        grid-template-columns: 220px 1fr;
        gap: 12px;
        align-items: center;
        margin-bottom: 10px;
        min-width: 640px;
        font-size: 0.8rem;
    }

    .gantt-label {
        color: var(--muted);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
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

    .gantt-progress {
        position: absolute;
        inset: 0 auto 0 0;
        background: rgba(255,255,255,0.25);
        border-radius: 4px;
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
</style>
@endpush

@section('content')
    @php
        $summary = $summary ?? [];
        $byCategory = $summary['by_category'] ?? [];
        $tokenValues = array_map(fn ($r) => (int) ($r['tokens'] ?? 0), $byCategory);
        $maxTokens = max(1, $tokenValues === [] ? 1 : max($tokenValues));
        $gantt = $gantt ?? ['bars' => [], 'milestones' => [], 'project_start' => null, 'project_end' => null];
        $start = $gantt['project_start'] ?? null;
        $end = $gantt['project_end'] ?? null;
        $span = 1;
        if ($start && $end) {
            $span = max(1, (new \DateTimeImmutable($end))->diff(new \DateTimeImmutable($start))->days + 1);
        }
        $statusClass = function (string $status): string {
            $s = strtolower($status);
            return match (true) {
                str_contains($s, 'done') => 'done',
                str_contains($s, 'review') => 'review',
                str_contains($s, 'progress') => 'progress',
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
            <h2>Lucille · usage &amp; schedule</h2>
            <p style="margin:4px 0 0;color:var(--muted);font-size:0.875rem;">Tokens, time, deadlines, and a living project Gantt.</p>
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
            <div class="metric-value">{{ number_format((int) ($summary['total_tokens'] ?? 0)) }}</div>
        </div>
        <div class="card metric">
            <div class="metric-label">Hours</div>
            <div class="metric-value">{{ $summary['total_hours'] ?? 0 }}</div>
        </div>
        <div class="card metric">
            <div class="metric-label">Minutes</div>
            <div class="metric-value">{{ $summary['total_minutes'] ?? 0 }}</div>
        </div>
    </div>

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
                            <div>{{ number_format((int) $row['tokens']) }}</div>
                        </div>
                    @endif
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
            <p style="margin:0 0 12px;color:var(--muted);font-size:0.8rem;">
                Window: {{ $start ?? '—' }} → {{ $end ?? '—' }} · updates with backlog progress and Lucille ledger data
            </p>
            <div class="gantt">
                @foreach ($gantt['bars'] as $bar)
                    <div class="gantt-row">
                        <div class="gantt-label" title="{{ $bar['label'] ?? '' }}">{{ $bar['label'] ?? '' }}</div>
                        <div class="gantt-track">
                            <div @class(['gantt-bar', $statusClass((string) ($bar['status'] ?? ''))])
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
        @endif
    </section>

    <section class="card panel">
        <h3>Recent ledger entries</h3>
        @if (($entries ?? []) === [])
            <div class="empty" style="padding:16px;">Empty ledger.</div>
        @else
            <table class="entries">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Category</th>
                        <th>User</th>
                        <th>Tokens</th>
                        <th>Minutes</th>
                        <th>Skill / Spec</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        <tr>
                            <td>{{ $entry['ts'] ?? '' }}</td>
                            <td>{{ $entry['category'] ?? '' }}</td>
                            <td>{{ $entry['user'] ?? '' }}</td>
                            <td>{{ number_format((int) ($entry['tokens'] ?? 0)) }}</td>
                            <td>{{ $entry['minutes'] ?? 0 }}</td>
                            <td>
                                {{ $entry['skill'] ?? '—' }}
                                @if (!empty($entry['spec']))
                                    · {{ $entry['spec'] }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection
