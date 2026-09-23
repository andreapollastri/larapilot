@extends('larapilot::dashboard.layout')

@section('title', 'Board')

@push('styles')
<style>
    .metrics {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .metric {
        padding: 18px 20px;
    }

    .metric-label {
        color: var(--muted);
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-weight: 600;
    }

    .metric-value {
        margin-top: 6px;
        font-size: 1.75rem;
        font-weight: 700;
        line-height: 1;
    }

    .board {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        align-items: start;
    }

    .board-scroll {
        width: 100%;
    }

    @media (max-width: 768px) {
        .board-scroll {
            margin: 0 -20px;
            padding: 0 20px 4px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scroll-snap-type: x proximity;
            scrollbar-width: thin;
        }

        .board {
            display: flex;
            flex-wrap: nowrap;
            gap: 16px;
            align-items: stretch;
            width: max-content;
            min-width: 100%;
        }

        .column {
            flex: 0 0 min(85vw, 300px);
            scroll-snap-align: start;
        }
    }

    .column {
        min-height: 120px;
    }

    .column-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 14px;
        border-bottom: 1px solid var(--border);
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .column-stats {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .column-count {
        color: var(--muted);
        font-weight: 600;
        font-size: 0.72rem;
        text-transform: none;
        letter-spacing: normal;
    }

    .column-body {
        padding: 12px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .board-tools {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 8px;
        align-items: end;
    }

    .board-tools label {
        display: grid;
        gap: 4px;
        flex: 1 1 150px;
        min-width: 0;
        font-size: 0.75rem;
        color: var(--muted);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .board-tools label:first-child {
        flex: 2 1 220px;
    }

    .board-tools input,
    .board-tools select {
        width: 100%;
        padding: 8px 10px;
        border-radius: 8px;
        border: 1px solid var(--border);
        background: var(--bg);
        color: var(--text);
        font-size: 0.875rem;
        font-weight: 400;
        text-transform: none;
        letter-spacing: normal;
    }

    .board-tools-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        min-height: 28px;
        margin-bottom: 16px;
    }

    .board-filter-count {
        margin: 0;
        color: var(--muted);
        font-size: 0.82rem;
    }

    .board-filter-clear {
        border: 1px solid var(--border);
        background: var(--surface);
        color: var(--text);
        border-radius: 999px;
        padding: 4px 12px;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
    }

    .board-filter-clear:hover {
        border-color: var(--accent);
        color: var(--accent);
    }

    @media (max-width: 768px) {
        .board-tools label,
        .board-tools label:first-child {
            flex-basis: 100%;
        }
    }

    .spec-card {
        position: relative;
        display: block;
        padding: 12px 14px;
        border-radius: 10px;
        border: 1px solid var(--border);
        background: color-mix(in srgb, var(--surface) 92%, var(--bg));
        color: inherit;
        text-decoration: none;
        transition: border-color 0.15s ease, transform 0.15s ease;
    }

    .board-tools-bar[hidden],
    .spec-card[hidden],
    .column[hidden],
    .points[hidden],
    .column-empty[hidden],
    .board-filter-count[hidden],
    .board-filter-clear[hidden] {
        display: none !important;
    }

    .spec-card:hover {
        border-color: var(--accent);
        transform: translateY(-1px);
        text-decoration: none;
    }

    .spec-card-hit {
        position: absolute;
        inset: 0;
        z-index: 1;
        border-radius: inherit;
        text-decoration: none;
    }

    .spec-card-hit:focus-visible {
        outline: 2px solid var(--accent);
        outline-offset: 2px;
    }

    .merge-commit-link {
        position: relative;
        z-index: 2;
        color: inherit;
    }

    .spec-card h3 {
        margin: 0 0 6px;
        font-size: 0.95rem;
    }

    .spec-card p {
        margin: 0;
        color: var(--muted);
        font-size: 0.82rem;
    }

    .spec-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 8px;
    }

    .spec-badges {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .points {
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--accent);
        background: var(--accent-soft);
        padding: 2px 8px;
        border-radius: 999px;
    }

    .task-progress {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 10px;
    }

    .task-progress-track {
        flex: 1;
        height: 5px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--border) 70%, transparent);
        overflow: hidden;
    }

    .task-progress-fill {
        height: 100%;
        border-radius: inherit;
        background: var(--status-done);
        transition: width 0.2s ease;
    }

    .task-progress-label {
        color: var(--muted);
        font-size: 0.72rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .merge-commit {
        margin-top: 8px;
        font-size: 0.72rem;
        font-weight: 700;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        color: var(--status-done);
    }

    .column-empty {
        padding: 16px;
        text-align: center;
        color: var(--muted);
        font-size: 0.85rem;
    }

    .mockup-indicator {
        margin-top: 8px;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #7c3aed;
    }

    .spec-indicators {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 8px;
        flex-wrap: wrap;
    }

    .spec-indicator {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.72rem;
        font-weight: 700;
        line-height: 1;
        padding: 4px 8px;
        border-radius: 999px;
        border: 1px solid var(--border);
        background: color-mix(in srgb, var(--surface) 88%, var(--bg));
    }

    .spec-indicator svg {
        width: 14px;
        height: 14px;
        flex-shrink: 0;
    }

    .spec-indicator--comments {
        color: var(--muted);
    }

    .spec-indicator--blocking {
        color: #b45309;
        border-color: color-mix(in srgb, #f59e0b 35%, var(--border));
        background: color-mix(in srgb, #f59e0b 10%, var(--surface));
    }
</style>
@endpush

@section('content')
    @php
        $epics = [];
        $prioritiesPresent = [];

        foreach ($columns as $columnSpecs) {
            foreach ($columnSpecs as $columnSpec) {
                $epicCode = (string) ($columnSpec['epic']['code'] ?? '');

                if ($epicCode !== '') {
                    $epicTitle = trim((string) ($columnSpec['epic']['title'] ?? ''));
                    $epics[$epicCode] = $epicTitle !== '' ? $epicTitle : $epicCode;
                }

                $priorityName = strtoupper(trim((string) ($columnSpec['priority'] ?? '')));

                if ($priorityName !== '') {
                    $prioritiesPresent[$priorityName] = true;
                }
            }
        }

        uasort($epics, static fn (string $left, string $right): int => strcasecmp($left, $right));

        $priorityRank = ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'];
        $extraPriorities = array_keys(array_diff_key($prioritiesPresent, array_flip($priorityRank)));
        sort($extraPriorities, SORT_STRING);
        $priorityOptions = array_merge(
            array_values(array_filter($priorityRank, static fn (string $name): bool => isset($prioritiesPresent[$name]))),
            $extraPriorities
        );
        $doneStatus = (string) ($workflow['done'] ?? 'DONE');
        $wipStatuses = implode('|', $workflow['wip'] ?? ['IN PROGRESS', 'REVIEW']);
        $specWord = static fn (int $count): string => $count === 1 ? 'spec' : 'specs';
    @endphp

    <section class="metrics">
        <div class="card metric">
            <div class="metric-label">Total specs</div>
            <div class="metric-value" data-metric="total" data-original="{{ $metrics['total'] ?? 0 }}">{{ $metrics['total'] ?? 0 }}</div>
        </div>
        <div class="card metric">
            <div class="metric-label">Done</div>
            <div class="metric-value" data-metric="done" data-original="{{ $metrics['done'] ?? 0 }}">{{ $metrics['done'] ?? 0 }}</div>
        </div>
        <div class="card metric">
            <div class="metric-label">Completion</div>
            <div class="metric-value" data-metric="completion" data-original="{{ $metrics['completion_rate'] ?? 0 }}%">{{ $metrics['completion_rate'] ?? 0 }}%</div>
        </div>
        <div class="card metric">
            <div class="metric-label">WIP</div>
            <div class="metric-value" data-metric="wip" data-original="{{ $metrics['wip'] ?? 0 }}">{{ $metrics['wip'] ?? 0 }}</div>
        </div>
    </section>

    @if (($metrics['total'] ?? 0) === 0)
        <div class="card empty">
            <p>No backlog specs yet. Run <code>/larapilot-spec</code> to create user stories.</p>
        </div>
    @else
        <form class="board-tools" id="board-tools" role="search">
            <label>
                Search
                <input type="search" id="board-q" placeholder="Code, title, epic, merge…" autocomplete="off">
            </label>
            <label>
                Priority
                <select id="board-priority">
                    <option value="">All</option>
                    @foreach ($priorityOptions as $priorityOption)
                        <option value="{{ $priorityOption }}">{{ $priorityOption }}</option>
                    @endforeach
                </select>
            </label>
            @if ($epics !== [])
                <label>
                    Epic
                    <select id="board-epic">
                        <option value="">All</option>
                        @foreach ($epics as $epicCode => $epicTitle)
                            <option value="{{ $epicCode }}">{{ $epicTitle }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <label>
                Status
                <select id="board-status">
                    <option value="">All</option>
                    @foreach ($statusOrder as $status)
                        <option value="{{ $status }}">{{ $status }}</option>
                    @endforeach
                </select>
            </label>
        </form>
        <div class="board-tools-bar" id="board-tools-bar" hidden>
            <p class="board-filter-count" id="board-filter-count" hidden></p>
            <button type="button" class="board-filter-clear" id="board-filter-clear" hidden>Clear</button>
        </div>

        <div class="board-scroll">
        <section
            class="board"
            id="board"
            data-done-status="{{ $doneStatus }}"
            data-wip-statuses="{{ $wipStatuses }}"
            data-total="{{ $metrics['total'] ?? 0 }}"
        >
            @foreach ($statusOrder as $status)
                @php
                    $items = $columns[$status] ?? [];
                    $columnPoints = array_sum(array_map(
                        fn (array $spec): int => max(0, (int) ($spec['points'] ?? 0)),
                        $items
                    ));
                    $badgeClass = match (strtoupper($status)) {
                        'TODO' => 'badge-todo',
                        'PLANNED' => 'badge-planned',
                        'IN PROGRESS' => 'badge-in-progress',
                        'REVIEW' => 'badge-review',
                        'DONE' => 'badge-done',
                        default => 'badge-todo',
                    };
                @endphp
                <article class="card column" data-status="{{ $status }}">
                    <div class="column-header">
                        <span class="badge {{ $badgeClass }}">{{ $status }}</span>
                        <div class="column-stats">
                            <span class="column-count" data-column-count data-original="{{ count($items) }}">{{ count($items) }} {{ $specWord(count($items)) }}</span>
                            <span class="points" data-column-points data-original="{{ $columnPoints }}" @if ($columnPoints === 0) hidden @endif>{{ $columnPoints }} SP</span>
                        </div>
                    </div>
                    <div class="column-body">
                        @forelse ($items as $spec)
                            @include('larapilot::dashboard.partials.spec-card', ['spec' => $spec])
                        @empty
                            <div class="column-empty">No specs</div>
                        @endforelse
                        @if (count($items) > 0)
                            <div class="column-empty" data-no-matches hidden>No matches</div>
                        @endif
                    </div>
                </article>
            @endforeach
        </section>
        </div>
    @endif
@endsection

@push('scripts')
<script>
    (() => {
        const form = document.getElementById('board-tools');
        const board = document.getElementById('board');

        if (!form || !board) {
            return;
        }

        const query = document.getElementById('board-q');
        const priority = document.getElementById('board-priority');
        const epic = document.getElementById('board-epic');
        const status = document.getElementById('board-status');
        const count = document.getElementById('board-filter-count');
        const clear = document.getElementById('board-filter-clear');
        const bar = document.getElementById('board-tools-bar');
        const cards = [...board.querySelectorAll('.spec-card')];
        const columns = [...board.querySelectorAll('.column')];
        const metrics = {
            total: document.querySelector('[data-metric="total"]'),
            done: document.querySelector('[data-metric="done"]'),
            completion: document.querySelector('[data-metric="completion"]'),
            wip: document.querySelector('[data-metric="wip"]'),
        };
        const doneStatus = board.dataset.doneStatus || 'DONE';
        const wipStatuses = (board.dataset.wipStatuses || '').split('|').filter(Boolean);
        const totalSpecs = Number(board.dataset.total || cards.length);

        const specWord = (value) => `${value} ${value === 1 ? 'spec' : 'specs'}`;

        const formatRate = (done, total) => {
            if (!total) {
                return '0%';
            }

            const rate = Math.round((done / total) * 1000) / 10;

            return `${Number.isInteger(rate) ? rate : rate.toFixed(1)}%`;
        };

        const filtering = () => {
            const needle = (query.value || '').trim();

            return needle !== ''
                || (priority && priority.value !== '')
                || (epic && epic.value !== '')
                || (status && status.value !== '');
        };

        const matches = (card) => {
            const needle = (query.value || '').trim().toLowerCase();

            if (needle !== '' && !(card.dataset.search || '').includes(needle)) {
                return false;
            }

            if (priority && priority.value !== '' && card.dataset.priority !== priority.value) {
                return false;
            }

            if (epic && epic.value !== '' && card.dataset.epic !== epic.value) {
                return false;
            }

            return true;
        };

        const restore = () => {
            cards.forEach((card) => {
                card.hidden = false;
            });

            columns.forEach((column) => {
                column.hidden = false;
                const columnCount = column.querySelector('[data-column-count]');
                const columnPoints = column.querySelector('[data-column-points]');
                const noMatches = column.querySelector('[data-no-matches]');
                const originalCount = Number(columnCount?.dataset.original || 0);
                const originalPoints = Number(columnPoints?.dataset.original || 0);

                if (columnCount) {
                    columnCount.textContent = specWord(originalCount);
                }

                if (columnPoints) {
                    columnPoints.hidden = originalPoints === 0;
                    columnPoints.textContent = `${originalPoints} SP`;
                }

                if (noMatches) {
                    noMatches.hidden = true;
                }
            });

            Object.values(metrics).forEach((node) => {
                if (node) {
                    node.textContent = node.dataset.original || '';
                }
            });

            if (bar) {
                bar.hidden = true;
            }

            if (count) {
                count.hidden = true;
            }

            if (clear) {
                clear.hidden = true;
            }
        };

        const apply = () => {
            if (!filtering()) {
                restore();
                return;
            }

            const statusValue = status ? status.value : '';
            let visibleTotal = 0;
            let visibleDone = 0;
            let visibleWip = 0;

            columns.forEach((column) => {
                const statusOk = statusValue === '' || column.dataset.status === statusValue;
                column.hidden = !statusOk;

                let visible = 0;
                let points = 0;

                column.querySelectorAll('.spec-card').forEach((card) => {
                    const ok = statusOk && matches(card);
                    card.hidden = !ok;

                    if (!ok) {
                        return;
                    }

                    visible += 1;
                    points += Number(card.dataset.points || 0);
                    visibleTotal += 1;

                    if (card.dataset.status === doneStatus) {
                        visibleDone += 1;
                    }

                    if (wipStatuses.includes(card.dataset.status || '')) {
                        visibleWip += 1;
                    }
                });

                const columnCount = column.querySelector('[data-column-count]');
                const columnPoints = column.querySelector('[data-column-points]');
                const noMatches = column.querySelector('[data-no-matches]');

                if (columnCount) {
                    columnCount.textContent = specWord(visible);
                }

                if (columnPoints) {
                    columnPoints.hidden = points === 0;
                    columnPoints.textContent = `${points} SP`;
                }

                if (noMatches) {
                    noMatches.hidden = !statusOk || visible > 0;
                }
            });

            if (metrics.total) {
                metrics.total.textContent = String(visibleTotal);
            }

            if (metrics.done) {
                metrics.done.textContent = String(visibleDone);
            }

            if (metrics.wip) {
                metrics.wip.textContent = String(visibleWip);
            }

            if (metrics.completion) {
                metrics.completion.textContent = formatRate(visibleDone, visibleTotal);
            }

            if (bar) {
                bar.hidden = false;
            }

            if (count) {
                count.hidden = false;
                count.textContent = `Showing ${visibleTotal} of ${totalSpecs}`;
            }

            if (clear) {
                clear.hidden = false;
            }
        };

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            apply();
        });

        [query, priority, epic, status].forEach((control) => {
            control?.addEventListener('input', apply);
            control?.addEventListener('change', apply);
        });

        clear?.addEventListener('click', () => {
            form.reset();
            apply();
        });
    })();
</script>
@endpush
