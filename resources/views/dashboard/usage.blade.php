@extends('larapilot::dashboard.layout')

@section('title', 'Usage')

@push('styles')
<style>
    .usage-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }

    .usage-top h2 {
        margin: 0 0 6px;
        font-size: 1.15rem;
    }

    .usage-top .sub {
        margin: 0;
        color: var(--muted);
        font-size: 0.875rem;
        max-width: 72ch;
        line-height: 1.5;
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
        white-space: nowrap;
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
    @endphp

    <div class="usage-top">
        <div>
            <h2>Lucille · Token usage</h2>
            <p class="sub">Tokens and hours logged by agents. Deadlines, epics, and the Gantt live on <a href="{{ route('larapilot.dashboard.plan') }}">Plan</a>.</p>
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
