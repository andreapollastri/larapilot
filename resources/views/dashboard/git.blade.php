@extends('larapilot::dashboard.layout')

@section('title', 'Git')

@push('styles')
<style>
    .git-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }

    .git-top h2 {
        margin: 0;
        font-size: 1.15rem;
    }

    .git-top .hint {
        margin: 6px 0 0;
        color: var(--muted);
        font-size: 0.8rem;
    }

    .git-filter {
        display: grid;
        gap: 4px;
        font-size: 0.75rem;
        color: var(--muted);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        min-width: min(100%, 280px);
    }

    .git-filter select {
        padding: 8px 12px;
        border-radius: 8px;
        border: 1px solid var(--border);
        background: var(--bg);
        color: var(--text);
        font-size: 0.875rem;
        font-weight: 400;
        text-transform: none;
        letter-spacing: normal;
    }

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
        margin: 0 0 6px;
        font-size: 0.95rem;
    }

    .panel .hint {
        margin: 0 0 16px;
        color: var(--muted);
        font-size: 0.8rem;
    }

    .heatmap {
        --week-count: {{ count($weeks) }};
        --gap: 3px;
        --dow-width: 24px;
        width: 100%;
    }

    .heatmap-grid {
        display: grid;
        grid-template-columns: var(--dow-width) minmax(0, 1fr);
        gap: 8px;
        align-items: start;
    }

    .heatmap-months {
        grid-column: 2;
        display: grid;
        grid-template-columns: repeat(var(--week-count), minmax(0, 1fr));
        gap: var(--gap);
        margin-bottom: 6px;
        color: var(--muted);
        font-size: 0.7rem;
        line-height: 1.2;
        min-width: 0;
    }

    .heatmap-month {
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
    }

    .heatmap-dow {
        grid-row: 2;
        display: grid;
        grid-template-rows: repeat(7, minmax(0, 1fr));
        gap: var(--gap);
        color: var(--muted);
        font-size: 0.65rem;
        align-self: stretch;
    }

    .heatmap-dow span {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding-right: 2px;
    }

    .heatmap-scroll {
        grid-row: 2;
        min-width: 0;
        overflow-x: auto;
        overflow-y: hidden;
        scrollbar-width: thin;
    }

    .heatmap-weeks {
        display: grid;
        grid-template-columns: repeat(var(--week-count), minmax(9px, 1fr));
        gap: var(--gap);
        width: 100%;
        min-width: min(100%, calc(var(--week-count) * 12px));
    }

    .heatmap-week {
        display: grid;
        grid-template-rows: repeat(7, minmax(0, 1fr));
        gap: var(--gap);
        min-width: 0;
    }

    .heatmap-cell {
        width: 100%;
        aspect-ratio: 1;
        border-radius: 2px;
        background: #ebedf0;
        min-height: 0;
    }

    .heatmap-cell.out {
        opacity: 0.35;
    }

    .heatmap-cell.level-1 { background: #9be9a8; }
    .heatmap-cell.level-2 { background: #40c463; }
    .heatmap-cell.level-3 { background: #30a14e; }
    .heatmap-cell.level-4 { background: #216e39; }

    .heatmap-legend {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 4px;
        margin-top: 12px;
        color: var(--muted);
        font-size: 0.72rem;
    }

    .heatmap-legend .heatmap-cell {
        width: 11px;
        height: 11px;
        flex: 0 0 11px;
    }

    .branch-list {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .branch-pill {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 999px;
        border: 1px solid var(--border);
        background: var(--bg);
        font-size: 0.78rem;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }

    @media (prefers-color-scheme: dark) {
        .heatmap-cell { background: #161b22; }
        .heatmap-cell.level-1 { background: #0e4429; }
        .heatmap-cell.level-2 { background: #006d32; }
        .heatmap-cell.level-3 { background: #26a641; }
        .heatmap-cell.level-4 { background: #39d353; }
    }
</style>
@endpush

@section('content')
    <div class="git-top">
        <div>
            <h2>Git history</h2>
            <p class="hint">
                Commits on every local branch for the last 12 months
                ({{ \Illuminate\Support\Carbon::parse($range_start)->format('M j, Y') }}
                – {{ \Illuminate\Support\Carbon::parse($range_end)->format('M j, Y') }}).
                @if ($origin_slug)
                    Remote: {{ $origin_slug }}
                @endif
            </p>
        </div>
        @if ($is_repository && $authors !== [])
            <form method="get" action="{{ route('larapilot.dashboard.git') }}">
                <label class="git-filter">
                    Developer
                    <select name="author" onchange="this.form.submit()" aria-label="Filter by developer">
                        <option value="" @selected($selected_author === null)>All developers</option>
                        @foreach ($authors as $author)
                            <option value="{{ $author['email'] }}" @selected($selected_author === $author['email'])>
                                {{ $author['name'] }} — {{ number_format($author['commits']) }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <noscript><button type="submit">Show</button></noscript>
            </form>
        @endif
    </div>

    @if (! $is_repository)
        <div class="card empty">This project is not a git repository yet.</div>
    @else
        <div class="metrics">
            <div class="card metric">
                <div class="metric-label">Contributions</div>
                <div class="metric-value">{{ number_format($total) }}</div>
            </div>
            <div class="card metric">
                <div class="metric-label">Developers</div>
                <div class="metric-value">{{ number_format(count($authors)) }}</div>
            </div>
            <div class="card metric">
                <div class="metric-label">Branches</div>
                <div class="metric-value">{{ number_format(count($branches)) }}</div>
            </div>
        </div>

        <section class="card panel" aria-label="Contribution graph">
            <h3>{{ number_format($total) }} {{ $total === 1 ? 'contribution' : 'contributions' }} in the last year</h3>
            <p class="hint">Each square is a day — recent on the right, older to the left. Darker green means more commits by {{ $selected_author ? collect($authors)->firstWhere('email', $selected_author)['name'] ?? $selected_author : 'all developers' }}.</p>

            <div class="heatmap">
                <div class="heatmap-grid">
                    <div class="heatmap-months" aria-hidden="true">
                        @foreach ($months as $month)
                            <span
                                class="heatmap-month"
                                style="grid-column: {{ $month['offset'] + 1 }} / span {{ $month['span'] }}"
                            >
                                @if ($month['span'] >= 2)
                                    {{ $month['label'] }}
                                @endif
                            </span>
                        @endforeach
                    </div>
                    <div class="heatmap-dow" aria-hidden="true">
                        <span></span>
                        <span>Mon</span>
                        <span></span>
                        <span>Wed</span>
                        <span></span>
                        <span>Fri</span>
                        <span></span>
                    </div>
                    <div class="heatmap-scroll" data-heatmap-scroll>
                        <div class="heatmap-weeks" role="img" aria-label="{{ number_format($total) }} contributions in the last year">
                            @foreach ($weeks as $week)
                                <div class="heatmap-week">
                                    @foreach ($week as $day)
                                        <span
                                            class="heatmap-cell level-{{ $day['level'] }}{{ $day['in_range'] ? '' : ' out' }}"
                                            title="{{ $day['title'] }}"
                                        ></span>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="heatmap-legend">
                    Less
                    <span class="heatmap-cell level-0" title="No contributions."></span>
                    <span class="heatmap-cell level-1" title="Low contributions."></span>
                    <span class="heatmap-cell level-2" title="Medium-low contributions."></span>
                    <span class="heatmap-cell level-3" title="Medium-high contributions."></span>
                    <span class="heatmap-cell level-4" title="High contributions."></span>
                    More
                </div>
            </div>
        </section>

        @if ($branches !== [])
            <section class="card panel">
                <h3>Local branches</h3>
                <p class="hint">Read from <code>git for-each-ref refs/heads</code>. The graph above includes commits from all of them.</p>
                <div class="branch-list">
                    @foreach ($branches as $branch)
                        <span class="branch-pill">{{ $branch }}</span>
                    @endforeach
                </div>
            </section>
        @endif
    @endif
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-heatmap-scroll]').forEach((el) => {
        el.scrollLeft = el.scrollWidth;
    });
</script>
@endpush
