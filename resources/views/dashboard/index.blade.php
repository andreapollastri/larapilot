@extends('larapilot::dashboard.layout')

@section('title', 'Board')

@section('main-class', 'is-wide')

@push('styles')
<style>
    /* phone: search on its own row, the selects side by side under it */
    .board-tools {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(96px, 1fr));
        align-items: end;
        gap: 10px;
        margin-bottom: 10px;
    }

    .board-tools .field:first-child { grid-column: 1 / -1; }

    @media (min-width: 860px) {
        .board-tools { display: flex; flex-wrap: wrap; gap: 12px; }
        .board-tools .field { flex: 1 1 150px; }
        .board-tools .field:first-child { flex: 2 1 240px; }
    }

    .board-tools-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        min-height: 32px;
        margin-bottom: 14px;
    }

    .board-filter-count {
        margin: 0;
        color: var(--muted);
        font-size: 0.82rem;
        font-variant-numeric: tabular-nums;
    }

    .board-filter-clear {
        min-height: 30px;
        padding: 0 12px;
        border: 1px solid var(--border-strong);
        border-radius: 999px;
        background: var(--surface);
        color: var(--text);
        font: inherit;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
    }

    .board-filter-clear:hover { border-color: var(--accent); color: var(--accent); }

    .board-scroll {
        width: auto;
        margin: 0 calc(var(--gutter) * -1);
        padding: 2px var(--gutter) 10px;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scroll-snap-type: x proximity;
        scroll-padding-inline: var(--gutter);
        scrollbar-width: thin;
    }

    .board {
        display: flex;
        flex-wrap: nowrap;
        align-items: flex-start;
        gap: 14px;
        width: max-content;
        min-width: 100%;
    }

    .column {
        flex: 0 0 min(84vw, 310px);
        min-height: 120px;
        background: var(--surface-2);
        box-shadow: none;
        scroll-snap-align: start;
    }

    @media (min-width: 1024px) {
        .board-scroll {
            margin: 0;
            padding: 0 0 10px;
            scroll-snap-type: none;
        }

        /* one row: columns share the width, and scroll sideways when a
           workflow has more statuses than the screen can hold */
        .board {
            display: grid;
            grid-auto-flow: column;
            grid-auto-columns: minmax(210px, 1fr);
            width: auto;
        }

        .column { flex: none; }
    }

    /* status on one line, its numbers under it: a long status name and a
       narrow column never fight for the same row */
    .column-header {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
        padding: 14px 14px 12px;
    }

    .column-stats {
        display: flex;
        align-items: center;
        gap: 8px;
        min-height: 20px;
        padding-left: 2px;
    }

    .column-count {
        color: var(--muted);
        font-size: 0.74rem;
        font-weight: 550;
        font-variant-numeric: tabular-nums;
    }

    .column-body {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding: 0 10px 10px;
    }

    .column-empty {
        padding: 18px 12px;
        border: 1px dashed var(--border-strong);
        border-radius: var(--radius-sm);
        text-align: center;
        color: var(--muted);
        font-size: 0.82rem;
    }

    .spec-card {
        position: relative;
        display: block;
        padding: 13px 14px;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--surface);
        color: inherit;
        transition: border-color 0.14s ease, box-shadow 0.14s ease, transform 0.14s ease;
    }

    .spec-card:hover,
    .spec-card:focus-within {
        border-color: color-mix(in srgb, var(--accent) 55%, var(--border));
        box-shadow: 0 6px 18px color-mix(in srgb, var(--text) 8%, transparent);
        transform: translateY(-1px);
    }

    .spec-card-hit {
        position: absolute;
        inset: 0;
        z-index: 1;
        border-radius: inherit;
    }

    .spec-card-hit:focus-visible { outline-offset: 2px; border-radius: var(--radius-sm); }

    .merge-commit-link {
        position: relative;
        z-index: 2;
        color: inherit;
    }

    .spec-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 6px 8px;
        margin-bottom: 7px;
    }

    .spec-meta strong {
        color: var(--muted);
        font-family: var(--mono);
        font-size: 0.74rem;
        font-weight: 600;
        letter-spacing: 0.01em;
        white-space: nowrap;
    }

    .spec-badges {
        display: flex;
        align-items: center;
        gap: 5px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .spec-card h3 {
        margin: 0;
        font-size: 0.93rem;
        font-weight: 600;
        line-height: 1.35;
        letter-spacing: -0.005em;
    }

    .spec-card p {
        margin: 6px 0 0;
        color: var(--muted);
        font-size: 0.79rem;
    }

    .task-progress {
        display: flex;
        align-items: center;
        gap: 9px;
        margin-top: 11px;
    }

    .task-progress-track {
        flex: 1;
        height: 4px;
        border-radius: 999px;
        background: var(--surface-3);
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
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .merge-commit {
        margin-top: 9px;
        color: var(--ok);
        font-family: var(--mono);
        font-size: 0.72rem;
        font-weight: 600;
    }

    .mockup-indicator {
        display: inline-flex;
        margin-top: 9px;
        padding: 2px 8px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--violet-fill) 14%, transparent);
        color: var(--violet);
        font-size: 0.66rem;
        font-weight: 650;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .spec-indicators {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 9px;
        flex-wrap: wrap;
    }

    .spec-indicator {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 8px;
        border: 1px solid var(--border);
        border-radius: 999px;
        background: var(--surface-2);
        font-size: 0.72rem;
        font-weight: 600;
        line-height: 1;
        font-variant-numeric: tabular-nums;
    }

    .spec-indicator svg { width: 13px; height: 13px; flex-shrink: 0; }
    .spec-indicator--comments { color: var(--muted); }

    .spec-indicator--blocking {
        color: var(--warn);
        border-color: color-mix(in srgb, var(--warn-fill) 40%, var(--border));
        background: color-mix(in srgb, var(--warn-fill) 11%, var(--surface));
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

    <header class="page-head">
        <div>
            <h2>Board</h2>
            <p class="sub">Every user story in the backlog, grouped by workflow status.</p>
        </div>
    </header>

    <section class="metrics" aria-label="Backlog summary">
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
            <label class="field">
                Search
                <input type="search" id="board-q" placeholder="Code, title, epic, merge…" autocomplete="off">
            </label>
            <label class="field">
                Priority
                <select id="board-priority">
                    <option value="">All</option>
                    @foreach ($priorityOptions as $priorityOption)
                        <option value="{{ $priorityOption }}">{{ $priorityOption }}</option>
                    @endforeach
                </select>
            </label>
            @if ($epics !== [])
                <label class="field">
                    Epic
                    <select id="board-epic">
                        <option value="">All</option>
                        @foreach ($epics as $epicCode => $epicTitle)
                            <option value="{{ $epicCode }}">{{ $epicTitle }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <label class="field">
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
