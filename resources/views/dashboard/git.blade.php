@extends('larapilot::dashboard.layout')

@section('title', 'Git')

@push('styles')
<style>
    .git-filter { min-width: min(100%, 280px); }

    .git-panel { margin-bottom: 18px; }

    .git-panel h3 {
        margin: 0 0 4px;
        font-size: 1rem;
    }

    .git-panel .hint { margin: 0 0 18px; max-width: 80ch; }

    .heatmap {
        --week-count: {{ count($weeks) }};
        --gap: 3px;
        --dow-width: 26px;
        width: 100%;
    }

    .heatmap-scroll {
        overflow-x: auto;
        overflow-y: hidden;
        scrollbar-width: thin;
        padding-bottom: 4px;
    }

    .heatmap-grid {
        display: grid;
        grid-template-columns: var(--dow-width) minmax(0, 1fr);
        gap: 6px 8px;
        align-items: start;
        /* a day stays at least 11px wide; the year scrolls on a phone */
        min-width: calc(var(--dow-width) + var(--week-count) * 14px);
    }

    .heatmap-months {
        grid-column: 2;
        display: grid;
        grid-template-columns: repeat(var(--week-count), minmax(0, 1fr));
        gap: var(--gap);
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

    /* The labels are laid over the rows instead of sizing them: on a phone
       a day is shorter than a line of text. The column stays in view while
       the year scrolls under it. */
    .heatmap-dow {
        grid-row: 2;
        position: sticky;
        left: 0;
        z-index: 1;
        align-self: stretch;
        background: var(--surface);
        color: var(--muted);
        font-size: 0.64rem;
    }

    .heatmap-dow span {
        position: absolute;
        left: 0;
        right: 0;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        height: calc((100% - 6 * var(--gap)) / 7);
        padding-right: 3px;
        line-height: 1;
    }

    .heatmap-dow span:nth-child(2) { top: calc((100% + var(--gap)) / 7); }
    .heatmap-dow span:nth-child(4) { top: calc((100% + var(--gap)) / 7 * 3); }
    .heatmap-dow span:nth-child(6) { top: calc((100% + var(--gap)) / 7 * 5); }

    .heatmap-weeks {
        grid-row: 2;
        display: grid;
        grid-template-columns: repeat(var(--week-count), minmax(0, 1fr));
        gap: var(--gap);
        min-width: 0;
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
        min-height: 0;
        border-radius: 3px;
        background: var(--heat-0);
    }

    .heatmap-cell.out { opacity: 0.35; }
    .heatmap-cell.level-1 { background: var(--heat-1); }
    .heatmap-cell.level-2 { background: var(--heat-2); }
    .heatmap-cell.level-3 { background: var(--heat-3); }
    .heatmap-cell.level-4 { background: var(--heat-4); }

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
        gap: 6px;
    }

    .branch-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 11px;
        border: 1px solid var(--border);
        border-radius: 999px;
        background: var(--surface-2);
        color: var(--text-2);
        font-family: var(--mono);
        font-size: 0.77rem;
        overflow-wrap: anywhere;
    }
</style>
@endpush

@section('content')
    <header class="page-head">
        <div>
            <h2>Git history</h2>
            <p class="sub">
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
                <label class="field git-filter">
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
                <noscript><button class="btn small" type="submit">Show</button></noscript>
            </form>
        @endif
    </header>

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

        <section class="card panel git-panel" aria-label="Contribution graph">
            <h3>{{ number_format($total) }} {{ $total === 1 ? 'contribution' : 'contributions' }} in the last year</h3>
            <p class="hint">Each square is a day — recent on the right, older to the left. Darker green means more commits by {{ $selected_author ? collect($authors)->firstWhere('email', $selected_author)['name'] ?? $selected_author : 'all developers' }}.</p>

            <div class="heatmap">
                <div class="heatmap-scroll" data-heatmap-scroll>
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
            <section class="card panel git-panel">
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
