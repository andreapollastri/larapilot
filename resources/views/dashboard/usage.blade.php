@extends('larapilot::dashboard.layout')

@section('title', 'Usage')

@push('styles')
<style>
    .usage-panel { margin-bottom: 18px; }

    .usage-panel h3 {
        margin: 0 0 4px;
        font-size: 1rem;
    }

    .usage-panel .hint { margin: 0 0 14px; font-variant-numeric: tabular-nums; }
    .usage-panel h3 + .bars,
    .usage-panel h3 + .filters,
    .usage-panel h3 + .empty { margin-top: 14px; }

    .bars {
        display: grid;
        gap: 12px;
    }

    .bar-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 6px 12px;
        align-items: center;
        font-size: 0.86rem;
    }

    .bar-row .bar-track { grid-column: 1 / -1; grid-row: 2; }
    .bar-row > :last-child { color: var(--muted); font-variant-numeric: tabular-nums; }

    @media (min-width: 640px) {
        .bar-row { grid-template-columns: 150px minmax(0, 1fr) 70px; }
        .bar-row .bar-track { grid-column: auto; grid-row: auto; }
        .bar-row > :last-child { text-align: right; }
    }

    .filters {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }

    .filters .field:first-child { grid-column: 1 / -1; }

    @media (min-width: 860px) {
        .filters { grid-template-columns: 2fr repeat(3, minmax(0, 1fr)); }
        .filters .field:first-child { grid-column: auto; }
    }

    .entries {
        width: 100%;
        min-width: 720px;
        border-collapse: collapse;
        font-size: 0.84rem;
    }

    .entries th,
    .entries td {
        padding: 10px 12px;
        border-bottom: 1px solid var(--border);
        text-align: left;
        vertical-align: top;
    }

    .entries th {
        color: var(--muted);
        font-size: 0.7rem;
        font-weight: 650;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .entries td:first-child,
    .entries td:nth-child(4),
    .entries td:nth-child(5) {
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .entries td:first-child { color: var(--text-2); }
    .entries td:last-child { white-space: nowrap; }

    .entries tbody tr:hover { background: var(--surface-2); }

    .pager {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-top: 14px;
        color: var(--muted);
        font-size: 0.8rem;
        font-variant-numeric: tabular-nums;
    }

    .pager > div { display: flex; gap: 6px; }

    .reason-list {
        margin: 0;
        padding-left: 18px;
        color: var(--text-2);
        font-size: 0.85rem;
        line-height: 1.55;
    }

    .reason-list li { margin: 3px 0; }

    .actuals-figures {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px 20px;
        margin: 14px 0 18px;
        padding-bottom: 18px;
        border-bottom: 1px solid var(--border);
    }

    @media (min-width: 860px) {
        .actuals-figures { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    }

    .actuals-figures .metric-value.is-lead { color: var(--accent-strong); }

    .actuals {
        width: 100%;
        min-width: 620px;
        border-collapse: collapse;
        font-size: 0.84rem;
    }

    .actuals th,
    .actuals td {
        padding: 10px 12px;
        border-bottom: 1px solid var(--border);
        text-align: right;
        vertical-align: top;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .actuals th {
        color: var(--muted);
        font-size: 0.7rem;
        font-weight: 650;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .actuals th:first-child,
    .actuals td:first-child { min-width: 180px; text-align: left; white-space: normal; }

    .actuals tbody tr:hover { background: var(--surface-2); }
    .actuals .spec-title { display: block; margin-top: 2px; color: var(--text-2); }
    .actuals .chips { margin-top: 6px; }
    .actuals .chip { padding: 1px 8px; font-size: 0.7rem; }
    .actuals .bar-track { width: 96px; height: 4px; margin: 7px 0 0 auto; }
    .actuals .bar-fill.is-over { background: var(--warn-fill); }
    .actuals .is-blank { color: var(--muted); }
    .usage-panel .actuals-note { margin: 14px 0 0; }
</style>
@endpush

@section('content')
    @php
        $summary = $summary ?? [];
        $byCategory = $summary['by_category'] ?? [];
        $tokenValues = array_map(fn ($r) => (int) ($r['tokens'] ?? 0), $byCategory);
        $maxTokens = max(1, $tokenValues === [] ? 1 : max($tokenValues));
        $zoey = $zoey ?? [];
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
        $actuals = is_array($actuals ?? null) ? $actuals : [];
        $actualRows = is_array($actuals['specs'] ?? null) ? $actuals['specs'] : [];
        $actualTotals = is_array($actuals['totals'] ?? null) ? $actuals['totals'] : [];
        $actualLimit = 50;
        $doneLabel = $actuals['statuses']['done'] ?? 'DONE';
        $inProgressLabel = $actuals['statuses']['in_progress'] ?? 'IN PROGRESS';
        $plainHours = fn (float|int $hours): string => rtrim(rtrim(number_format((float) $hours, 1, '.', ''), '0'), '.') ?: '0';
        $logged = function (mixed $stamp): string {
            $stamp = (string) $stamp;

            if ($stamp === '') {
                return '';
            }

            try {
                return (new \DateTimeImmutable($stamp))->format('M j, Y · H:i');
            } catch (\Exception) {
                return $stamp;
            }
        };
    @endphp

    <header class="page-head">
        <div>
            <h2>Lucille · Token usage</h2>
            <p class="sub">Tokens and hours logged by agents. Deadlines, epics, and the Gantt live on <a href="{{ route('larapilot.dashboard.plan') }}">Plan</a>.</p>
        </div>
        <div class="page-actions">
            <a class="btn ghost" href="{{ route('larapilot.dashboard.usage.report') }}">@include('larapilot::dashboard.partials.icon', ['name' => 'download'])Download report.md</a>
        </div>
    </header>

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
    </div>

    <section class="card panel usage-panel" id="actuals-panel">
        <h3>Estimate vs build</h3>
        @if ($actualRows === [])
            <div class="empty" style="padding: 24px 12px;">No spec delivered yet. A spec shows here once it is {{ $doneLabel }}, with the time it spent {{ $inProgressLabel }}.</div>
        @else
            <p class="hint">
                {{ $actualTotals['delivered'] ?? 0 }} of {{ $actualTotals['backlog'] ?? 0 }} specs delivered
                · {{ $actualTotals['timed'] ?? 0 }} with a build time
            </p>
            <div class="actuals-figures">
                <div>
                    <div class="metric-label">Estimated</div>
                    <div class="metric-value">{{ $plainHours($actualTotals['estimate_hours'] ?? 0) }} h</div>
                    <div class="metric-note">Hours of the plans, for the specs with a build time</div>
                </div>
                <div>
                    <div class="metric-label">Build</div>
                    <div class="metric-value">{{ $actualTotals['build_display'] ?? '—' }}</div>
                    <div class="metric-note">Time spent {{ $inProgressLabel }}, pauses included</div>
                </div>
                <div>
                    <div class="metric-label">Estimate ÷ build</div>
                    <div class="metric-value is-lead">{{ $actualTotals['ratio_display'] ?? '—' }}</div>
                    <div class="metric-note">
                        @if (($actualTotals['ratio_display'] ?? null) === null)
                            Given from {{ $actualTotals['min_timed_specs'] ?? 3 }} specs with a build time
                        @else
                            An estimate the agent wrote, not hours a person worked
                        @endif
                    </div>
                </div>
                <div>
                    <div class="metric-label">Tokens</div>
                    <div class="metric-value">{{ $formatTokens((int) ($actualTotals['tokens'] ?? 0)) }}</div>
                    <div class="metric-note">Logged for {{ $actualTotals['specs_with_tokens'] ?? 0 }} of {{ $actualTotals['delivered'] ?? 0 }} delivered specs</div>
                </div>
            </div>
            <div class="table-wrap">
            <table class="actuals" id="actuals-table">
                <thead>
                    <tr>
                        <th>Spec</th>
                        <th>Estimate</th>
                        <th>Build</th>
                        <th>Ratio</th>
                        <th>In review</th>
                        <th>Tokens</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (array_slice($actualRows, 0, $actualLimit) as $row)
                        @php
                            $estimateMinutes = ((float) ($row['estimate_hours'] ?? 0)) * 60;
                            $share = ($row['build_minutes'] ?? null) !== null && $estimateMinutes > 0
                                ? ((float) $row['build_minutes']) / $estimateMinutes * 100
                                : null;
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('larapilot.dashboard.spec', ['code' => $row['code']]) }}"><strong>{{ $row['code'] }}</strong></a>
                                <span class="spec-title">{{ $row['title'] }}</span>
                                @if (($row['reworks'] ?? 0) > 0 || ($row['restarts'] ?? 0) > 0 || ($row['estimate_from'] ?? 'plan') !== 'plan' || empty($row['timed']))
                                    <span class="chips">
                                        @if (($row['reworks'] ?? 0) > 0)
                                            <span class="chip stale" title="Review sent it back for changes">sent back ×{{ $row['reworks'] }}</span>
                                        @endif
                                        @if (($row['restarts'] ?? 0) > 0)
                                            <span class="chip stale" title="It left {{ $inProgressLabel }} without reaching review, and was started again">started over ×{{ $row['restarts'] }}</span>
                                        @endif
                                        @if (($row['estimate_from'] ?? 'plan') === 'points')
                                            <span class="chip" title="No plan: the estimate is the story points">from story points</span>
                                        @elseif (($row['estimate_from'] ?? 'plan') === 'unsized')
                                            <span class="chip" title="No plan and no story points: counted at the default size">default size</span>
                                        @endif
                                        @if (empty($row['timed']))
                                            <span class="chip" title="The backlog holds no {{ $inProgressLabel }} step for this spec">no build time</span>
                                        @endif
                                    </span>
                                @endif
                            </td>
                            <td>{{ $plainHours($row['estimate_hours'] ?? 0) }} h</td>
                            <td @class(['is-blank' => $share === null])>
                                {{ $row['build_display'] ?? '—' }}
                                @if ($share !== null)
                                    <div class="bar-track" title="The build is {{ $share < 1 ? 'under 1' : (int) round($share) }}% of the estimate">
                                        <div @class(['bar-fill', 'is-over' => $share > 100]) style="width: {{ max(2, min(100, (int) round($share))) }}%"></div>
                                    </div>
                                @endif
                            </td>
                            <td @class(['is-blank' => ($row['ratio_display'] ?? null) === null])>{{ $row['ratio_display'] ?? '—' }}</td>
                            <td @class(['is-blank' => ($row['review_display'] ?? null) === null])>{{ $row['review_display'] ?? '—' }}</td>
                            <td @class(['is-blank' => ($row['tokens'] ?? 0) <= 0])>{{ ($row['tokens'] ?? 0) > 0 ? $formatTokens((int) $row['tokens']) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
            <p class="hint actuals-note">
                @if (count($actualRows) > $actualLimit)
                    The {{ $actualLimit }} newest of {{ count($actualRows) }} delivered specs; the report holds them all.
                @endif
                <strong>Estimate</strong> is the hours of the plan's tasks, or the story points of a spec with no plan, before the PM/QA buffer.
                <strong>Build</strong> is the time between <code>spec-start</code> and <code>spec-review</code>: the spec was {{ $inProgressLabel }}, whether or not someone was working.
                <strong>In review</strong> is the wait until {{ $doneLabel }}, not the time a person spent reviewing.
                <strong>Tokens</strong> are the ledger entries logged with <code>--spec</code>.
            </p>
        @endif
    </section>

    <section class="card panel usage-panel">
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

    <section class="card panel usage-panel">
        <h3>By category</h3>
        @if (($summary['entry_count'] ?? 0) === 0)
            <div class="empty" style="padding: 24px 12px;">No ledger entries yet. Agents log with <code>larapilot:usage-log</code>.</div>
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

    <section class="card panel usage-panel" id="ledger-panel">
        <h3>Ledger history</h3>
        @if (($entries ?? []) === [])
            <div class="empty" style="padding: 24px 12px;">Empty ledger.</div>
        @else
            <div class="filters">
                <label class="field">Search
                    <input type="search" id="ledger-q" placeholder="skill, spec, note…" autocomplete="off">
                </label>
                <label class="field">Executor
                    <select id="ledger-user">
                        <option value="">All</option>
                        @foreach (($entry_users ?? []) as $user)
                            <option value="{{ $user }}">{{ $user }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">Category
                    <select id="ledger-category">
                        <option value="">All</option>
                        @foreach (($entry_categories ?? []) as $category)
                            <option value="{{ $category }}">{{ $category }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">Estimate
                    <select id="ledger-estimated">
                        <option value="">All</option>
                        <option value="1">Estimated</option>
                        <option value="0">Measured</option>
                    </select>
                </label>
            </div>
            <div class="table-wrap">
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
                            <td title="{{ $entry['ts'] ?? '' }}">{{ $logged($entry['ts'] ?? '') }}</td>
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
            </div>
            <div class="pager">
                <span id="ledger-count">Showing 0</span>
                <div>
                    <button type="button" class="btn ghost small" id="ledger-prev">Prev</button>
                    <button type="button" class="btn ghost small" id="ledger-next">Next</button>
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
