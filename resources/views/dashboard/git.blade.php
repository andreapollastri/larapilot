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

    /* ---- branches and history ----
       Four hues and a neutral, checked for colour-blind separation on both
       surfaces. The colour is never alone: every row names its branch. */
    .git-page {
        --git-main: #2a78d6;
        --git-develop: #e87ba4;
        --git-work: #008300;
        --git-ship: #eda100;
        --git-other: #8594a3;
        --lane: 20px;
        --row: 48px;
    }

    @media (prefers-color-scheme: dark) {
        :root:not([data-theme="light"]) .git-page {
            --git-main: #3987e5;
            --git-develop: #d55181;
            --git-work: #008300;
            --git-ship: #c98500;
            --git-other: #8f9dab;
        }
    }

    :root[data-theme="dark"] .git-page {
        --git-main: #3987e5;
        --git-develop: #d55181;
        --git-work: #008300;
        --git-ship: #c98500;
        --git-other: #8f9dab;
    }

    .k-main { --kind: var(--git-main); }
    .k-develop { --kind: var(--git-develop); }
    .k-work { --kind: var(--git-work); }
    .k-ship { --kind: var(--git-ship); }
    .k-other { --kind: var(--git-other); }

    .git-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 8px 16px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }

    .git-head h3 { margin: 0 0 4px; }
    .git-head .hint { margin: 0; max-width: 78ch; }

    .git-filters { display: flex; align-items: end; gap: 10px; flex-wrap: wrap; }
    .git-filters .field { min-width: min(100%, 200px); }

    .swatch {
        flex: none;
        width: 10px;
        height: 10px;
        border-radius: 999px;
        background: var(--kind);
    }

    .swatch.is-line { width: 16px; height: 3px; border-radius: 2px; }

    .legend {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 18px;
        margin: 0 0 14px;
        padding: 0;
        list-style: none;
        color: var(--text-2);
        font-size: 0.8rem;
    }

    .legend li { display: inline-flex; align-items: center; gap: 7px; }
    .legend small { color: var(--muted); font-size: 0.74rem; font-variant-numeric: tabular-nums; }

    /* ---- branch table ---- */
    .branch-table { width: 100%; border-collapse: collapse; font-size: 0.86rem; }

    .branch-table th {
        padding: 0 12px 8px;
        border-bottom: 1px solid var(--border);
        color: var(--muted);
        font-size: 0.68rem;
        font-weight: 650;
        letter-spacing: 0.08em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .branch-table td {
        padding: 11px 12px;
        border-bottom: 1px solid var(--border);
        vertical-align: top;
    }

    .branch-table tr:last-child td { border-bottom: 0; }
    .branch-table th:first-child, .branch-table td:first-child { padding-left: 0; }
    .branch-table th:last-child, .branch-table td:last-child { padding-right: 0; }
    .branch-table tr.is-current td { background: color-mix(in srgb, var(--accent-soft) 55%, transparent); }
    .branch-table tr.is-current td:first-child { box-shadow: inset 3px 0 0 var(--accent); padding-left: 10px; }

    .branch-name {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        min-width: 0;
    }

    .branch-name code {
        padding: 0;
        background: transparent;
        color: var(--text);
        font-size: 0.84rem;
        font-weight: 600;
        overflow-wrap: anywhere;
    }

    .branch-type {
        padding: 1px 7px;
        border-radius: 999px;
        background: var(--surface-3);
        color: var(--muted);
        font-size: 0.64rem;
        font-weight: 650;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .branch-here {
        padding: 1px 8px;
        border-radius: 999px;
        background: var(--accent);
        color: var(--accent-contrast);
        font-size: 0.64rem;
        font-weight: 650;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .branch-sub { display: block; margin-top: 3px; color: var(--muted); font-size: 0.78rem; line-height: 1.45; }
    .branch-subject { display: block; color: var(--text-2); overflow-wrap: anywhere; }

    .sha {
        font-family: var(--mono);
        font-size: 0.76rem;
        color: var(--muted);
        white-space: nowrap;
    }

    a.sha { color: var(--accent); }

    /* how far a branch is from the branch it is heading for */
    .gap { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 2px; width: 132px; margin-top: 6px; }
    .gap-side { display: flex; height: 6px; border-radius: 3px; background: var(--surface-3); overflow: hidden; }
    .gap-side.is-behind { justify-content: flex-end; }
    .gap-fill { display: block; height: 100%; min-width: 3px; }
    .gap-side.is-behind .gap-fill { background: var(--git-other); border-radius: 3px 0 0 3px; }
    .gap-side.is-ahead .gap-fill { background: var(--accent); border-radius: 0 3px 3px 0; }
    .gap-key { display: flex; justify-content: space-between; width: 132px; margin-top: 3px; color: var(--muted); font-size: 0.68rem; font-variant-numeric: tabular-nums; }

    .state { display: inline-flex; align-items: center; gap: 6px; color: var(--text-2); }
    .state .icon { width: 15px; height: 15px; flex: none; color: var(--muted); }
    .state.is-merged .icon { color: var(--ok); }
    .state.is-local .icon, .state.is-gone .icon { color: var(--warn); }

    .branch-more { margin-top: 14px; border-top: 1px solid var(--border); padding-top: 12px; }
    .branch-more > summary { cursor: pointer; color: var(--text-2); font-size: 0.86rem; font-weight: 600; }
    .branch-more > summary small { color: var(--muted); font-weight: 400; }
    .branch-more[open] > summary { margin-bottom: 12px; }

    @media (max-width: 759px) {
        .branch-table, .branch-table tbody, .branch-table tr, .branch-table td { display: block; }
        .branch-table thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
        .branch-table tr { padding: 12px 0; border-bottom: 1px solid var(--border); }
        .branch-table tr:last-child { border-bottom: 0; }
        .branch-table td { padding: 0; border: 0; }
        .branch-table td + td { margin-top: 8px; }
        .branch-table td[data-label]::before {
            content: attr(data-label);
            display: block;
            margin-bottom: 2px;
            color: var(--muted);
            font-size: 0.66rem;
            font-weight: 650;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .branch-table tr.is-current { padding-left: 10px; box-shadow: inset 3px 0 0 var(--accent); }
        .branch-table tr.is-current td { background: transparent; }
        .branch-table tr.is-current td:first-child { box-shadow: none; padding-left: 0; }
    }

    /* ---- commit graph ---- */
    .graph-scroll { overflow-x: auto; scrollbar-width: thin; -webkit-overflow-scrolling: touch; }

    /* Text gives way before the page scrolls: only a history with many
       branches side by side is wider than a phone. */
    .graph { margin: 0; padding: 0; list-style: none; min-width: calc(var(--lanes) * var(--lane) + 190px); }

    .commit {
        display: grid;
        grid-template-columns: calc(var(--lanes) * var(--lane)) minmax(0, 1fr);
        align-items: stretch;
        column-gap: 12px;
        height: var(--row);
    }

    .commit:hover { background: var(--surface-2); }
    .commit.is-dim .commit-text, .commit.is-dim .commit-side { opacity: 0.42; }

    .commit svg { display: block; width: calc(var(--lanes) * var(--lane)); height: var(--row); overflow: visible; }
    .commit path { fill: none; stroke: var(--kind); stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
    .commit .ring { fill: var(--surface); }
    .commit:hover .ring { fill: var(--surface-2); }
    .commit .dot { fill: var(--kind); }
    .commit .dot.is-merge { fill: var(--surface); stroke: var(--kind); stroke-width: 2; }
    .commit .halo { fill: none; stroke: var(--kind); stroke-width: 1.5; opacity: 0.55; }
    .commit.is-dim .dot { opacity: 0.5; }

    .commit-body {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        align-items: center;
        column-gap: 16px;
        min-width: 0;
        padding-right: 4px;
        border-bottom: 1px solid color-mix(in srgb, var(--border) 60%, transparent);
    }

    .commit:last-child .commit-body { border-bottom: 0; }

    .commit-text { min-width: 0; }

    .commit-line {
        display: flex;
        align-items: center;
        gap: 6px;
        min-width: 0;
        line-height: 1.3;
    }

    .commit-subject {
        min-width: 0;
        overflow: hidden;
        color: var(--text);
        font-size: 0.86rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .commit.is-merge .commit-subject { color: var(--text-2); }

    .commit-meta {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 2px;
        min-width: 0;
        color: var(--muted);
        font-size: 0.74rem;
        white-space: nowrap;
    }

    .commit-meta .on {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .commit-meta .on span:last-child { overflow: hidden; text-overflow: ellipsis; }
    .commit-meta .swatch { width: 7px; height: 7px; }
    .commit-meta .sep { opacity: 0.6; }
    .commit-meta .narrow { overflow: hidden; text-overflow: ellipsis; }

    .commit-side { display: none; }

    .refs { display: inline-flex; align-items: center; gap: 6px; min-width: 0; }
    .refs.is-wide { display: none; flex: none; }
    .refs.is-narrow { flex: 0 1 auto; overflow: hidden; }
    .refs.is-narrow .ref { max-width: 150px; }
    /* the labels keep their width; the author and the date give way */
    .commit-meta.has-refs .narrow { flex: 1 1 0; min-width: 0; }

    /* under the message the labels already say which branch it is */
    .commit-meta.has-refs .on, .commit-meta.has-refs .on + .narrow-sep { display: none; }

    .ref {
        flex: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        max-width: 190px;
        padding: 1px 8px;
        border: 1px solid var(--border-strong);
        border-radius: 999px;
        background: var(--surface);
        color: var(--text-2);
        font-family: var(--mono);
        font-size: 0.68rem;
        font-weight: 600;
        line-height: 1.5;
        white-space: nowrap;
    }

    .ref > span:last-child { overflow: hidden; text-overflow: ellipsis; }
    .ref .icon { width: 11px; height: 11px; flex: none; color: var(--muted); }
    .ref .swatch { width: 7px; height: 7px; }
    .ref.is-current { border-color: var(--accent); background: var(--accent); color: var(--accent-contrast); }
    .ref.is-current .swatch { box-shadow: 0 0 0 1.5px var(--accent-contrast); }
    .ref.is-tag { border-style: dashed; }
    .ref.is-remote { color: var(--muted); font-weight: 500; }

    .spec-link {
        flex: none;
        padding: 0 6px;
        border-radius: 5px;
        background: var(--accent-soft);
        color: var(--accent-strong);
        font-family: var(--mono);
        font-size: 0.68rem;
        font-weight: 600;
        line-height: 1.6;
    }

    @media (min-width: 900px) {
        .commit-body { grid-template-columns: minmax(0, 1fr) 150px 96px 64px; }
        .commit-side { display: block; min-width: 0; overflow: hidden; color: var(--text-2); font-size: 0.8rem; text-overflow: ellipsis; white-space: nowrap; }
        .commit-side.is-date { color: var(--muted); font-variant-numeric: tabular-nums; }
        .commit-side.is-sha { text-align: right; }
        .commit-meta .narrow, .commit-meta .narrow-sep { display: none; }
        .refs.is-wide { display: inline-flex; }
        .refs.is-narrow { display: none; }
        .commit-meta.has-refs .on { display: inline-flex; }
    }

    .graph-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px 16px;
        flex-wrap: wrap;
        margin-top: 14px;
        padding-top: 14px;
        border-top: 1px solid var(--border);
        color: var(--muted);
        font-size: 0.82rem;
    }

    .how-to-read { margin-top: 14px; }
    .how-to-read > summary { cursor: pointer; color: var(--text-2); font-size: 0.84rem; font-weight: 600; }
    .how-to-read ul { margin: 10px 0 0; padding-left: 1.1rem; color: var(--text-2); font-size: 0.84rem; line-height: 1.6; max-width: 84ch; }
</style>
@endpush

@section('content')
    @php
        $measured = array_filter($graph['branches'], static fn (array $branch): bool => $branch['state'] !== null);
        $isClosed = static fn (array $branch): bool => in_array($branch['state']['key'] ?? '', ['merged', 'level'], true) && ! $branch['current'];
        $mergedBranches = array_values(array_filter($graph['branches'], $isClosed));
        $activeBranches = array_values(array_filter($graph['branches'], static fn (array $branch): bool => ! $isClosed($branch)));
        $open = count(array_filter($measured, static fn (array $branch): bool => ($branch['ahead'] ?? 0) > 0));
        $closed = count($mergedBranches);
        $gapScale = max(1, ...array_map(static fn (array $branch): int => max((int) ($branch['ahead'] ?? 0), (int) ($branch['behind'] ?? 0)), $graph['branches'] ?: [['ahead' => 1]]));
    @endphp
    <div class="git-page">
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
            <form method="get" action="{{ route('larapilot.dashboard.git') }}" class="git-filters">
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
                @if ($graph['available'] && $graph['total'] > \Larapilot\Services\GitGraphService::DEFAULT_LIMIT)
                    <label class="field">
                        History shown
                        <select name="commits" onchange="this.form.submit()" aria-label="How many commits the history shows">
                            @foreach ([150, 300, 600] as $depth)
                                <option value="{{ $depth }}" @selected($graph['limit'] === $depth)>Last {{ $depth }} commits</option>
                            @endforeach
                        </select>
                    </label>
                @endif
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
                @if ($open + $closed > 0)
                    <div class="metric-note">{{ $open }} with work to merge · {{ $closed }} merged</div>
                @endif
            </div>
            <div class="card metric">
                <div class="metric-label">You are on</div>
                <div class="metric-value" style="font-size: 1.05rem; font-family: var(--mono); letter-spacing: 0">{{ $graph['current'] ?? ($graph['detached'] ? $graph['head'] : '—') }}</div>
                @if ($graph['detached'])
                    <div class="metric-note">A commit, not a branch (detached HEAD)</div>
                @endif
            </div>
            <div class="card metric">
                <div class="metric-label">Tags</div>
                <div class="metric-value">{{ number_format(count($graph['tags'])) }}</div>
                @if ($graph['tags'] !== [])
                    <div class="metric-note">Latest {{ $graph['tags'][0]['name'] }} · {{ $graph['tags'][0]['date_label'] }}</div>
                @endif
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

        @if ($graph['available'])
            <section class="card panel git-panel" aria-labelledby="branches-title">
                <div class="git-head">
                    <div>
                        <h3 id="branches-title">Branches</h3>
                        <p class="hint">
                            Every local branch, newest work first, measured against the branch it is heading for:
                            @if ($graph['develop'])
                                work goes into <code>{{ $graph['develop'] }}</code>, what ships goes into <code>{{ $graph['primary'] }}</code>.
                            @elseif ($graph['primary'])
                                everything goes into <code>{{ $graph['primary'] }}</code>.
                            @else
                                no main branch was found, so nothing is measured.
                            @endif
                        </p>
                    </div>
                </div>

                @php
                    $branchRow = static function (array $branch, int $scale): array {
                        $ahead = (int) ($branch['ahead'] ?? 0);
                        $behind = (int) ($branch['behind'] ?? 0);

                        return [
                            'ahead' => $scale > 0 ? max($ahead > 0 ? 4 : 0, (int) round($ahead / $scale * 100)) : 0,
                            'behind' => $scale > 0 ? max($behind > 0 ? 4 : 0, (int) round($behind / $scale * 100)) : 0,
                        ];
                    };
                    $stateIcon = ['merged' => 'check', 'level' => 'check', 'ahead' => 'merge', 'diverged' => 'merge'];
                    $upstreamIcon = ['local' => 'info', 'gone' => 'info', 'synced' => 'check', 'push' => 'upload', 'pull' => 'download', 'diverged' => 'merge'];
                @endphp

                @foreach (['active' => $activeBranches, 'merged' => $mergedBranches] as $group => $list)
                    @continue($list === [])

                    @if ($group === 'merged')
                        <details class="branch-more">
                            <summary>{{ count($list) }} merged {{ count($list) === 1 ? 'branch' : 'branches' }} <small>— their work is already in the branch they were heading for, so they can be deleted</small></summary>
                    @endif

                    <table class="branch-table">
                        <thead>
                            <tr>
                                <th scope="col">Branch</th>
                                <th scope="col">Last commit</th>
                                <th scope="col">Against its target</th>
                                <th scope="col">On the remote</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($list as $branch)
                                @php $bars = $branchRow($branch, $gapScale); @endphp
                                <tr @class(['is-current' => $branch['current']])>
                                    <td>
                                        <span class="branch-name k-{{ $branch['kind'] }}">
                                            <span class="swatch" aria-hidden="true"></span>
                                            <code>{{ $branch['name'] }}</code>
                                            <span class="branch-type">{{ $branch['type'] }}</span>
                                            @if ($branch['current'])
                                                <span class="branch-here" title="The branch checked out in the project right now">You are here</span>
                                            @endif
                                            @if ($branch['spec_url'])
                                                <a class="spec-link" href="{{ $branch['spec_url'] }}" title="Open the spec this branch works on">{{ $branch['spec'] }}</a>
                                            @endif
                                        </span>
                                    </td>
                                    <td data-label="Last commit">
                                        <span class="branch-subject">{{ $branch['subject'] }}</span>
                                        <span class="branch-sub">
                                            {{ $branch['author'] }} · <span title="{{ $branch['date_label'] }}">{{ $branch['ago'] }}</span> ·
                                            @if ($branch['url'])
                                                <a class="sha" href="{{ $branch['url'] }}" target="_blank" rel="noopener noreferrer">{{ $branch['short_sha'] }}</a>
                                            @else
                                                <span class="sha">{{ $branch['short_sha'] }}</span>
                                            @endif
                                        </span>
                                    </td>
                                    <td data-label="Against its target">
                                        @if ($branch['target'] === null)
                                            <span class="state">@include('larapilot::dashboard.partials.icon', ['name' => 'tag'])The line the others are measured against</span>
                                        @elseif ($branch['state'] === null)
                                            <span class="hint">Not measured</span>
                                        @else
                                            <span class="state is-{{ $branch['state']['key'] }}">@include('larapilot::dashboard.partials.icon', ['name' => $stateIcon[$branch['state']['key']] ?? 'info']){{ $branch['state']['label'] }}</span>
                                            @if ($branch['ahead'] > 0 || $branch['behind'] > 0)
                                                <span class="gap" role="img" aria-label="{{ $branch['behind'] }} behind, {{ $branch['ahead'] }} ahead of {{ $branch['target'] }}">
                                                    <span class="gap-side is-behind"><span class="gap-fill" style="width: {{ $bars['behind'] }}%"></span></span>
                                                    <span class="gap-side is-ahead"><span class="gap-fill" style="width: {{ $bars['ahead'] }}%"></span></span>
                                                </span>
                                                <span class="gap-key" aria-hidden="true"><span>{{ $branch['behind'] }} behind</span><span>{{ $branch['ahead'] }} ahead</span></span>
                                            @endif
                                        @endif
                                    </td>
                                    <td data-label="On the remote">
                                        <span class="state is-{{ $branch['upstream_state']['key'] }}">@include('larapilot::dashboard.partials.icon', ['name' => $upstreamIcon[$branch['upstream_state']['key']] ?? 'info']){{ $branch['upstream_state']['label'] }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if ($group === 'merged')
                        </details>
                    @endif
                @endforeach

                @if ($graph['unmeasured'] > 0)
                    <p class="hint" style="margin: 12px 0 0">Only the {{ \Larapilot\Services\GitGraphService::MEASURED_BRANCHES }} newest branches are measured against their target; {{ $graph['unmeasured'] }} older {{ $graph['unmeasured'] === 1 ? 'one is' : 'ones are' }} listed without it.</p>
                @endif

                @if ($graph['remote_branches'] !== [])
                    <details class="branch-more">
                        <summary>{{ count($graph['remote_branches']) }} on the remote only <small>— pushed by someone, never checked out here</small></summary>
                        <table class="branch-table">
                            <thead>
                                <tr>
                                    <th scope="col">Branch</th>
                                    <th scope="col">Last commit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($graph['remote_branches'] as $branch)
                                    <tr>
                                        <td>
                                            <span class="branch-name k-{{ $branch['kind'] }}">
                                                <span class="swatch" aria-hidden="true"></span>
                                                <code>{{ $branch['name'] }}</code>
                                                <span class="branch-type">{{ $branch['type'] }}</span>
                                                @if ($branch['spec_url'])
                                                    <a class="spec-link" href="{{ $branch['spec_url'] }}">{{ $branch['spec'] }}</a>
                                                @endif
                                            </span>
                                        </td>
                                        <td data-label="Last commit">
                                            <span class="branch-subject">{{ $branch['subject'] }}</span>
                                            <span class="branch-sub">{{ $branch['author'] }} · <span title="{{ $branch['date_label'] }}">{{ $branch['ago'] }}</span> · <span class="sha">{{ $branch['short_sha'] }}</span></span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </details>
                @endif
            </section>

            <section class="card panel git-panel" aria-labelledby="history-title">
                <div class="git-head">
                    <div>
                        <h3 id="history-title">History</h3>
                        <p class="hint">
                            The {{ $graph['truncated'] ? 'last '.number_format(count($graph['commits'])).' of '.number_format($graph['total']) : number_format(count($graph['commits'])) }}
                            {{ count($graph['commits']) === 1 ? 'commit' : 'commits' }}, newest at the top. Each dot is a commit on the branch it was made on;
                            a line that leaves its lane is a branch being cut or merged.
                            @if ($selected_author)
                                Commits by other developers are faded.
                            @endif
                        </p>
                    </div>
                </div>

                <ul class="legend" aria-label="What the colours stand for">
                    @foreach ($graph['kinds'] as $kind => $count)
                        <li class="k-{{ $kind }}">
                            <span class="swatch is-line" aria-hidden="true"></span>
                            @php
                                $named = match ($kind) {
                                    'main' => $graph['primary'],
                                    'develop' => $graph['develop'],
                                    'work' => 'feature, bugfix',
                                    'ship' => 'release, hotfix',
                                    default => null,
                                };
                            @endphp
                            <span>{{ $graph['kind_labels'][$kind] }}{{ $named ? ' — '.$named : '' }}</span>
                            <small>{{ number_format($count) }} {{ $count === 1 ? 'commit' : 'commits' }}</small>
                        </li>
                    @endforeach
                </ul>

                @php
                    $lane = 20;
                    $row = 48;
                    $mid = $row / 2;
                    $x = static fn (int $index): float => $index * $lane + $lane / 2;
                    $refIcon = ['tag' => 'tag', 'remote' => 'external'];
                @endphp

                <div class="graph-scroll">
                    <ol class="graph" style="--lanes: {{ max(1, $graph['lanes']) }}">
                        @foreach ($graph['commits'] as $commit)
                            @php
                                $cx = $x($commit['lane']);
                                $here = collect($commit['refs'])->contains(fn (array $ref): bool => in_array($ref['type'], ['current', 'head'], true));
                                $on = $commit['branch'] ?? 'a branch that was merged and deleted';
                            @endphp
                            <li @class(['commit', 'is-merge' => $commit['is_merge'], 'is-dim' => $commit['dim']])>
                                <svg viewBox="0 0 {{ max(1, $graph['lanes']) * $lane }} {{ $row }}" aria-hidden="true" focusable="false">
                                    @foreach ($commit['through'] as $line)
                                        <path class="k-{{ $line['kind'] }}" d="M{{ $x($line['lane']) }} 0V{{ $row }}"/>
                                    @endforeach
                                    @foreach ($commit['in'] as $line)
                                        @if ($line['lane'] === $commit['lane'])
                                            <path class="k-{{ $line['kind'] }}" d="M{{ $cx }} 0V{{ $mid }}"/>
                                        @else
                                            <path class="k-{{ $line['kind'] }}" d="M{{ $x($line['lane']) }} 0C{{ $x($line['lane']) }} {{ $mid * 0.7 }} {{ $cx }} {{ $mid * 0.3 }} {{ $cx }} {{ $mid }}"/>
                                        @endif
                                    @endforeach
                                    @foreach ($commit['out'] as $line)
                                        @if ($line['lane'] === $commit['lane'])
                                            <path class="k-{{ $line['kind'] }}" d="M{{ $cx }} {{ $mid }}V{{ $row }}"/>
                                        @else
                                            <path class="k-{{ $line['kind'] }}" d="M{{ $cx }} {{ $mid }}C{{ $cx }} {{ $mid + $mid * 0.7 }} {{ $x($line['lane']) }} {{ $mid + $mid * 0.3 }} {{ $x($line['lane']) }} {{ $row }}"/>
                                        @endif
                                    @endforeach
                                    <g class="k-{{ $commit['kind'] }}">
                                        @if ($here)
                                            <circle class="halo" cx="{{ $cx }}" cy="{{ $mid }}" r="9"/>
                                        @endif
                                        <circle class="ring" cx="{{ $cx }}" cy="{{ $mid }}" r="7"/>
                                        <circle @class(['dot', 'is-merge' => $commit['is_merge']]) cx="{{ $cx }}" cy="{{ $mid }}" r="{{ $commit['is_merge'] ? 4 : 5 }}"/>
                                    </g>
                                </svg>
                                <div class="commit-body">
                                    <div class="commit-text">
                                        {{-- The labels sit beside the message where there is room for
                                             both, and under it on a narrow screen. Written once. --}}
                                        @php ob_start(); @endphp
                                        @foreach ($commit['refs'] as $ref)
                                            @php $refKind = $ref['kind']; @endphp
                                            <span @class(['ref', 'is-current' => in_array($ref['type'], ['current', 'head'], true), 'is-tag' => $ref['type'] === 'tag', 'is-remote' => $ref['type'] === 'remote', $refKind ? 'k-'.$refKind : '']) title="{{ ['current' => 'The branch checked out right now', 'head' => 'The commit checked out right now', 'branch' => 'Branch', 'remote' => 'Branch on the remote', 'tag' => 'Tag'][$ref['type']] }}: {{ $ref['name'] }}">
                                                @if ($refKind)
                                                    <span class="swatch" aria-hidden="true"></span>
                                                @elseif (isset($refIcon[$ref['type']]))
                                                    @include('larapilot::dashboard.partials.icon', ['name' => $refIcon[$ref['type']]])
                                                @endif
                                                <span>{{ $ref['name'] }}</span>
                                            </span>
                                        @endforeach
                                        @php $refs = trim((string) ob_get_clean()); @endphp
                                        <div class="commit-line">
                                            @if ($refs !== '')
                                                <span class="refs is-wide">{!! $refs !!}</span>
                                            @endif
                                            <span class="commit-subject" title="{{ $commit['subject'] }}">{{ $commit['subject'] }}</span>
                                            @if ($commit['spec_url'])
                                                <a class="spec-link" href="{{ $commit['spec_url'] }}" title="Open the spec">{{ $commit['spec'] }}</a>
                                            @endif
                                        </div>
                                        <div @class(['commit-meta', 'has-refs' => $refs !== ''])>
                                            @if ($refs !== '')
                                                <span class="refs is-narrow" aria-hidden="true">{!! $refs !!}</span>
                                            @endif
                                            <span class="on k-{{ $commit['kind'] }}" title="Made on {{ $on }}">
                                                <span class="swatch" aria-hidden="true"></span>
                                                <span>{{ $commit['is_merge'] ? 'merge on ' : '' }}{{ $commit['branch'] ?? 'merged branch' }}{{ $commit['gone'] && $commit['branch'] ? ' (deleted)' : '' }}</span>
                                            </span>
                                            <span class="sep narrow-sep" aria-hidden="true">·</span>
                                            <span class="narrow">{{ $commit['author'] }} · {{ $commit['date_label'] }}</span>
                                        </div>
                                    </div>
                                    <span class="commit-side" title="{{ $commit['email'] }}">{{ $commit['author'] }}</span>
                                    <span class="commit-side is-date" title="{{ $commit['date_label'] }} {{ $commit['time_label'] }} · {{ $commit['ago'] }}">{{ $commit['date_label'] }}</span>
                                    <span class="commit-side is-sha">
                                        @if ($commit['url'])
                                            <a class="sha" href="{{ $commit['url'] }}" target="_blank" rel="noopener noreferrer" title="Open this commit on the remote">{{ $commit['short_sha'] }}</a>
                                        @else
                                            <span class="sha">{{ $commit['short_sha'] }}</span>
                                        @endif
                                    </span>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>

                @if ($graph['truncated'])
                    <div class="graph-foot">
                        <span>Older history is not drawn: {{ number_format($graph['total'] - count($graph['commits'])) }} more {{ $graph['total'] - count($graph['commits']) === 1 ? 'commit' : 'commits' }}. Lines that run off the bottom continue there.</span>
                        @if ($graph['limit'] < \Larapilot\Services\GitGraphService::MAX_LIMIT)
                            <a class="btn ghost small" href="{{ route('larapilot.dashboard.git', array_filter(['author' => $selected_author, 'commits' => min(\Larapilot\Services\GitGraphService::MAX_LIMIT, $graph['limit'] * 2)])) }}#history-title">Show {{ min(\Larapilot\Services\GitGraphService::MAX_LIMIT, $graph['limit'] * 2) }} commits</a>
                        @endif
                    </div>
                @endif

                <details class="how-to-read">
                    <summary>How to read this</summary>
                    <ul>
                        <li><strong>A column is a line of work.</strong> The main line is always the first column{{ $graph['develop'] ? ', the integration branch the second' : '' }}; every other branch takes the next free one.</li>
                        <li><strong>A filled dot is a commit</strong> — one saved change. <strong>A hollow dot is a merge</strong>: the point where a branch was brought into another.</li>
                        <li><strong>A curve leaving a dot downwards</strong> shows where that branch came from. <strong>A curve arriving from above</strong> shows a branch that started from that commit.</li>
                        <li><strong>A label</strong> marks the last commit of a branch. The filled one is the branch checked out right now. A dashed label is a tag — a released version.</li>
                        <li><strong>A branch that was merged and deleted</strong> still shows its commits, named from the message of the merge that closed it.</li>
                    </ul>
                </details>
            </section>
        @endif
    @endif
    </div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-heatmap-scroll]').forEach((el) => {
        el.scrollLeft = el.scrollWidth;
    });
</script>
@endpush
