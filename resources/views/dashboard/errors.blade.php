@extends('larapilot::dashboard.layout')

@section('title', 'Errors')

@push('styles')
<style>
    .errors-page { display: flex; flex-direction: column; gap: 20px; }
    .errors-page .page-head, .errors-page .metrics { margin-bottom: 0; }

    .verdict {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 16px 18px;
        border: 1px solid color-mix(in srgb, var(--tone) 40%, var(--border));
        border-radius: var(--radius);
        background: color-mix(in srgb, var(--tone) 9%, var(--surface));
        --tone: var(--warn-fill);
    }

    .verdict.is-pass { --tone: var(--ok-fill); }
    .verdict.is-fail { --tone: var(--danger-fill); }
    .verdict.is-calm { --tone: var(--accent); }
    .verdict .icon { flex: none; width: 22px; height: 22px; margin-top: 1px; color: color-mix(in srgb, var(--tone) 62%, var(--text)); }
    .verdict strong { display: block; font-size: 1.02rem; font-weight: 650; }
    .verdict p { margin: 3px 0 0; color: var(--text-2); font-size: 0.9rem; line-height: 1.5; max-width: 84ch; }

    .metric-value { font-variant-numeric: tabular-nums; }

    /* one series, so no legend: the title says what the bars are */
    .days { padding: 18px 20px 14px; }
    .days h3 { margin: 0 0 2px; font-size: 1rem; }
    .days .hint { margin: 0 0 14px; }

    .days-plot {
        display: grid;
        grid-template-columns: repeat(var(--days), minmax(0, 1fr));
        align-items: end;
        gap: 2px;
        height: 120px;
        padding: 0;
        margin: 0;
        list-style: none;
        border-bottom: 1px solid var(--border-strong);
    }

    .days-plot li {
        position: relative;
        display: flex;
        align-items: flex-end;
        justify-content: center;
        height: 100%;
        outline: none;
    }

    .days-plot .bar {
        width: min(100%, 26px);
        height: var(--height);
        min-height: 0;
        border-radius: 4px 4px 0 0;
        background: var(--bar);
    }

    .days-plot li.is-empty .bar { height: 2px; border-radius: 0; background: var(--border-strong); }
    .days-plot li:is(:hover, :focus-visible) .bar { background: var(--bar-strong); }
    .days-plot li:is(:hover, :focus-visible)::before { content: ''; position: absolute; inset: 0; background: color-mix(in srgb, var(--text) 5%, transparent); border-radius: 4px 4px 0 0; }

    .days-plot .tip {
        position: absolute;
        bottom: calc(100% + 6px);
        left: 50%;
        z-index: 2;
        display: none;
        padding: 6px 10px;
        border: 1px solid var(--border-strong);
        border-radius: var(--radius-xs);
        background: var(--surface);
        box-shadow: 0 6px 18px color-mix(in srgb, var(--text) 14%, transparent);
        color: var(--text);
        font-size: 0.78rem;
        line-height: 1.35;
        white-space: nowrap;
        transform: translateX(-50%);
        pointer-events: none;
    }

    .days-plot .tip b { display: block; font-variant-numeric: tabular-nums; }
    .days-plot li:is(:hover, :focus-visible) .tip { display: block; }
    .days-plot li:nth-child(-n+2) .tip { left: 0; transform: none; }
    .days-plot li:nth-last-child(-n+2) .tip { left: auto; right: 0; transform: none; }

    .days-axis {
        display: flex;
        justify-content: space-between;
        margin-top: 6px;
        color: var(--muted);
        font-size: 0.74rem;
        font-variant-numeric: tabular-nums;
    }

    .days { --bar: #2a78d6; --bar-strong: #1c5cab; }

    @media (prefers-color-scheme: dark) {
        :root:not([data-theme="light"]) .days { --bar: #3987e5; --bar-strong: #6aa6ee; }
    }

    :root[data-theme="dark"] .days { --bar: #3987e5; --bar-strong: #6aa6ee; }

    .days details { margin-top: 12px; font-size: 0.84rem; }
    .days details summary { color: var(--muted); cursor: pointer; }
    .days details table { margin-top: 8px; width: auto; font-size: 0.84rem; font-variant-numeric: tabular-nums; }
    .days details :is(th, td) { padding: 4px 18px 4px 0; text-align: left; border: 0; }
    .days details td:last-child, .days details th:last-child { text-align: right; padding-right: 0; }

    .tools {
        display: flex;
        align-items: end;
        justify-content: space-between;
        gap: 10px 16px;
        flex-wrap: wrap;
    }

    .tools .field { flex: 1 1 260px; max-width: 420px; }
    .toggles { display: flex; flex-wrap: wrap; gap: 6px; }

    .toggle {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 36px;
        padding: 0 13px;
        border: 1px solid var(--border-strong);
        border-radius: 999px;
        background: var(--surface);
        color: var(--text-2);
        font: inherit;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
    }

    .toggle b { color: var(--muted); font-weight: 600; font-variant-numeric: tabular-nums; }
    .toggle:hover { border-color: var(--accent); color: var(--accent); }

    .toggle[aria-pressed="true"] {
        border-color: color-mix(in srgb, var(--accent) 55%, var(--border));
        background: var(--accent-soft);
        color: var(--accent-strong);
    }

    .findings { padding: 0; overflow: hidden; }
    .finding { border-top: 1px solid var(--border); }
    .finding:first-child { border-top: 0; }
    .finding[hidden] { display: none; }

    .finding > summary {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        align-items: center;
        gap: 8px 14px;
        padding: 14px 18px;
        cursor: pointer;
        list-style: none;
    }

    .finding > summary::-webkit-details-marker { display: none; }
    .finding > summary:hover { background: var(--surface-2); }
    .finding[open] > summary { background: var(--surface-2); }

    /* how many times: the first thing to know about an error */
    .times {
        display: inline-flex;
        align-items: baseline;
        justify-content: center;
        gap: 3px;
        flex: none;
        min-width: 64px;
        padding: 4px 10px;
        border-radius: var(--radius-xs);
        background: var(--surface-3);
        color: var(--text);
        font-size: 0.95rem;
        font-weight: 650;
        font-variant-numeric: tabular-nums;
    }

    .times small { color: var(--muted); font-size: 0.68rem; font-weight: 600; }

    .finding-title { min-width: 0; }
    .finding-title strong { display: block; font-size: 0.95rem; font-weight: 600; overflow-wrap: anywhere; }
    .finding-title small { display: block; margin-top: 2px; color: var(--muted); font-size: 0.78rem; line-height: 1.4; overflow-wrap: anywhere; }
    .finding-title code { padding: 0; background: transparent; font-size: 0.76rem; }

    .state {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 10px;
        border-radius: 999px;
        border: 1px solid var(--border);
        background: var(--surface);
        color: var(--text-2);
        font-size: 0.72rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .state.is-new { border-color: color-mix(in srgb, var(--warn-fill) 55%, var(--border)); color: var(--warn); }
    .state.is-in_backlog { border-color: color-mix(in srgb, var(--accent) 45%, var(--border)); color: var(--accent-strong); }
    .state.is-ignored { color: var(--muted); }
    .state.is-returned { border-color: color-mix(in srgb, var(--danger-fill) 55%, var(--border)); color: var(--danger); }

    @media (max-width: 639px) {
        .finding > summary { grid-template-columns: minmax(0, 1fr) auto; }
        .finding > summary .times { justify-self: start; }
        .finding > summary .finding-title { grid-column: 1 / -1; grid-row: 2; }
    }

    .finding-body {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 16px 28px;
        padding: 4px 18px 20px;
        background: var(--surface-2);
    }

    @media (min-width: 900px) {
        .finding-body { grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); }
    }

    .finding-body h4 {
        margin: 0 0 4px;
        color: var(--muted);
        font-size: 0.68rem;
        font-weight: 650;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .finding-body p { margin: 0 0 14px; font-size: 0.9rem; line-height: 1.6; overflow-wrap: anywhere; }
    .finding-body p:last-child { margin-bottom: 0; }
    .finding-body .said { font-family: var(--mono); font-size: 0.82rem; }
    .finding-facts { margin: 0; font-size: 0.86rem; }
    .finding-facts div { display: grid; grid-template-columns: 108px minmax(0, 1fr); gap: 10px; padding: 7px 0; border-top: 1px solid var(--border); }
    .finding-facts div:first-child { border-top: 0; padding-top: 0; }
    .finding-facts dt { color: var(--muted); }
    .finding-facts dd { margin: 0; overflow-wrap: anywhere; }
    .finding-facts code { padding: 0; background: transparent; font-size: 0.8rem; }

    .none { padding: 30px 20px; text-align: center; color: var(--muted); }
    .none[hidden] { display: none; }

    .closed-list { margin: 0; padding: 0; list-style: none; font-size: 0.88rem; }
    .closed-list li { display: flex; flex-wrap: wrap; gap: 4px 10px; padding: 8px 0; border-top: 1px solid var(--border); overflow-wrap: anywhere; }
    .closed-list li:first-child { border-top: 0; }
    .closed-list small { color: var(--muted); }
</style>
@endpush

@section('content')
    @php
        $service = \Larapilot\Services\BoogleService::class;
        $read = is_array($boogle ?? null) ? $boogle : null;
        $all = $read['errors'] ?? [];
        $bugs = array_values(array_filter($all, static fn (array $error): bool => $error['kind'] === $service::ERROR));
        $outages = array_values(array_filter($all, static fn (array $error): bool => $error['kind'] === $service::OUTAGE));
        $project = $read['project'] ?? null;
        $counts = $read['counts'] ?? [];
        $days = $read['days'] ?? [];
        $peak = max(1, ...array_column($days, 'count') ?: [1]);
        $thrownLately = array_sum(array_column($days, 'count'));

        $when = static fn (?string $iso): string => $iso ? \Illuminate\Support\Carbon::parse($iso)->format('M j, Y · H:i') : '—';
        $day = static fn (?string $iso): string => $iso ? \Illuminate\Support\Carbon::parse($iso)->format('M j') : '—';
        $times = static fn (int $number): string => number_format($number).' '.($number === 1 ? 'time' : 'times');
        $stateLabel = ['new' => 'No decision yet', 'in_backlog' => 'In the backlog', 'ignored' => 'Left as it is'];

        $tone = match (true) {
            $read === null => 'warn',
            ($counts['returned'] ?? 0) > 0 => 'fail',
            ($counts['new'] ?? 0) > 0 => 'warn',
            ($counts['errors'] ?? 0) === 0 && ($counts['outages'] ?? 0) === 0 => 'pass',
            default => 'calm',
        };

        $headline = match ($tone) {
            'fail' => 'An error came back after its fix',
            'warn' => 'There are errors nobody decided about',
            'pass' => 'Nothing is open',
            default => 'Every open error has a decision',
        };
    @endphp

    <div class="errors-page">
        <header class="page-head">
            <div>
                <h2>Errors</h2>
                @if ($enabled)
                    <p class="sub">What the running application threw, as Boogle recorded it: one row for each bug, however many times it happened, with what was decided about it.</p>
                @else
                    <p class="sub">Boogle records the exceptions the running application throws, and whether it answers. Larapilot reads those errors and brings each bug into the workflow.</p>
                    <p class="sub">Use <a href="https://boogle.web.ap.it/">Boogle</a> for this project: send the exceptions with <code>andreapollastri/boogle-client</code>, put the address and token in <code>.env</code>, then turn the link on with <code>php artisan larapilot:settings-set --boogle=YES</code>.</p>
                @endif
                @if ($project)
                    <div class="chips" style="margin-top: 12px">
                        <span class="chip current">{{ $project['title'] }}</span>
                        @if ($project['group'] !== '')
                            <span class="chip">{{ $project['group'] }}</span>
                        @endif
                        <span class="chip">{{ $project['uptime'] ? 'Uptime watched' : 'Uptime not watched' }}</span>
                        <span class="chip">Read {{ $when($read['fetched_at']) }}</span>
                    </div>
                @endif
            </div>
            @if ($enabled)
                <div class="page-actions">
                    <a class="btn ghost" href="{{ route('larapilot.dashboard.errors', ['refresh' => 1]) }}" title="Ask Boogle again instead of showing what was read a few minutes ago">@include('larapilot::dashboard.partials.icon', ['name' => 'refresh'])Read again</a>
                    @if ($read)
                        <a class="btn ghost" href="{{ route('larapilot.dashboard.errors.report') }}">@include('larapilot::dashboard.partials.icon', ['name' => 'download'])Download report (.md)</a>
                    @endif
                </div>
            @endif
        </header>

        @if ($enabled && $error)
            <div class="flash flash--error" role="alert">
                <strong>{{ $error }}</strong>
                @if ($hint)
                    <span>{{ $hint }}</span>
                @endif
            </div>
            @if (! empty($status['hints']))
                <section class="card panel">
                    <h3 style="margin: 0 0 8px; font-size: 1rem">What to check</h3>
                    <ul class="notes" style="margin: 0; padding-left: 1.1rem; color: var(--text-2); font-size: 0.9rem; line-height: 1.6">
                        @foreach ($status['hints'] as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                    <p class="hint" style="margin: 12px 0 0">Check from the terminal: <code>php artisan larapilot:boogle-status</code></p>
                </section>
            @endif
        @elseif ($enabled)
            <div @class(['verdict', 'is-'.$tone]) role="status">
                @include('larapilot::dashboard.partials.icon', ['name' => $tone === 'pass' ? 'check' : 'info'])
                <div>
                    <strong>{{ $headline }}</strong>
                    <p>{{ $read['summary'] }} An error in the backlog is not fixed yet: it stays here until Boogle holds it as fixed.</p>
                </div>
            </div>

            <section class="metrics" aria-label="Open errors in numbers">
                <div class="card metric">
                    <div class="metric-label">Open errors</div>
                    <div class="metric-value">{{ number_format($counts['errors']) }}</div>
                    <div class="metric-note">{{ $counts['errors'] === 1 ? 'bug' : 'bugs' }}, thrown {{ $times($counts['occurrences']) }}</div>
                </div>
                <div class="card metric">
                    <div class="metric-label">No decision yet</div>
                    <div class="metric-value">{{ number_format($counts['new']) }}</div>
                    <div class="metric-note">to hand to triage</div>
                </div>
                <div class="card metric">
                    <div class="metric-label">In the backlog</div>
                    <div class="metric-value">{{ number_format($counts['in_backlog']) }}</div>
                    <div class="metric-note">{{ $counts['returned'] > 0 ? $counts['returned'].' back after the fix' : 'a spec fixes each one' }}</div>
                </div>
                <div class="card metric">
                    <div class="metric-label">Outages</div>
                    <div class="metric-value">{{ number_format($counts['outages']) }}</div>
                    <div class="metric-note">{{ $project['uptime'] ? 'times the application did not answer' : 'the uptime monitor is off in Boogle' }}</div>
                </div>
            </section>

            @if ($bugs !== [])
                <section class="card days" aria-labelledby="days-title">
                    <h3 id="days-title">Errors thrown, day by day</h3>
                    <p class="hint">The open errors over the last {{ count($days) }} days: {{ $times($thrownLately) }} in all. What was fixed and closed in Boogle is not counted.</p>
                    <ul class="days-plot" style="--days: {{ count($days) }}" role="img" aria-label="Errors thrown on each of the last {{ count($days) }} days. The most in one day: {{ $peak }}.">
                        @foreach ($days as $point)
                            <li @class(['is-empty' => $point['count'] === 0]) tabindex="0" style="--height: {{ round($point['count'] / $peak * 100, 2) }}%">
                                <span class="bar"></span>
                                <span class="tip"><b>{{ $times($point['count']) }}</b>{{ \Illuminate\Support\Carbon::parse($point['date'])->format('D, M j') }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <div class="days-axis">
                        <span>{{ $day($days[0]['date'] ?? null) }}</span>
                        <span>Most in a day: {{ number_format($peak) }}</span>
                        <span>Today</span>
                    </div>
                    <details>
                        <summary>The same numbers as a table</summary>
                        <table>
                            <thead><tr><th>Day</th><th>Thrown</th></tr></thead>
                            <tbody>
                                @foreach ($days as $point)
                                    <tr><td>{{ \Illuminate\Support\Carbon::parse($point['date'])->format('D, M j') }}</td><td>{{ number_format($point['count']) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </details>
                </section>
            @endif

            @if ($bugs === [])
                <div class="card empty">
                    <p>Boogle holds no open error for <strong>{{ $project['title'] }}</strong>.</p>
                </div>
            @else
                <div class="tools">
                    <label class="field">
                        Find an error
                        <input type="search" id="finding-filter" placeholder="A class, a file, a route, a code…" autocomplete="off">
                    </label>
                    <div class="toggles" role="group" aria-label="Show by decision">
                        <button type="button" class="toggle" data-state="" aria-pressed="true">All <b>{{ $counts['errors'] }}</b></button>
                        <button type="button" class="toggle" data-state="new" aria-pressed="false">No decision yet <b>{{ $counts['new'] }}</b></button>
                        <button type="button" class="toggle" data-state="in_backlog" aria-pressed="false">In the backlog <b>{{ $counts['in_backlog'] }}</b></button>
                        <button type="button" class="toggle" data-state="ignored" aria-pressed="false">Left as they are <b>{{ $counts['ignored'] }}</b></button>
                        @if ($counts['returned'] > 0)
                            <button type="button" class="toggle" data-state="returned" aria-pressed="false">Back after a fix <b>{{ $counts['returned'] }}</b></button>
                        @endif
                    </div>
                </div>

                <section class="card findings" aria-label="Open errors, the ones thrown the most first">
                    @foreach ($bugs as $item)
                        <details class="finding" data-state="{{ $item['state'] }}" data-returned="{{ $item['returned'] ? '1' : '0' }}" data-find="{{ strtolower($item['class'].' '.$item['message'].' '.($item['where'] ?? '').' '.($item['request'] ?? '').' '.implode(' ', $item['codes']).' '.($item['spec'] ?? '').' '.$item['key']) }}">
                            <summary>
                                <span class="times" title="Thrown {{ $times($item['count']) }}">{{ number_format($item['count']) }}<small>×</small></span>
                                <span class="finding-title">
                                    <strong>{{ $item['short'] }}{{ $item['message'] !== '' ? ': '.\Illuminate\Support\Str::limit($item['message'], 110) : '' }}</strong>
                                    <small>{{ implode(', ', array_slice($item['codes'], 0, 3)) }}{{ count($item['codes']) > 3 ? ' +'.(count($item['codes']) - 3) : '' }}{{ $item['codes'] !== [] && $item['where'] !== null ? ' · ' : '' }}<code>{{ $item['where'] }}</code> · last {{ \Illuminate\Support\Carbon::parse($item['last_seen'])->diffForHumans() }}</small>
                                </span>
                                @if ($item['returned'])
                                    <span class="state is-returned">Back after {{ $item['spec'] }}</span>
                                @else
                                    <span class="state is-{{ $item['state'] }}">{{ $item['state'] === 'in_backlog' ? $item['spec'] : $stateLabel[$item['state']] }}</span>
                                @endif
                            </summary>
                            <div class="finding-body">
                                <div>
                                    @if ($item['message'] !== '')
                                        <h4>What it said</h4>
                                        <p class="said">{{ $item['message'] }}</p>
                                    @endif
                                    <h4>Decision</h4>
                                    <p>
                                        @if ($item['returned'])
                                            Fixed by <a href="{{ $item['spec_url'] }}">{{ $item['spec'] }}</a> and closed in Boogle on {{ $when($item['resolved_at']) }}, then thrown again. The fix did not hold: run <code>/larapilot-boogle</code> to hand it to triage again.
                                        @elseif ($item['state'] === 'in_backlog')
                                            In the backlog as <a href="{{ $item['spec_url'] }}">{{ $item['spec'] }}</a>{{ $item['spec_status'] ? ', now '.$item['spec_status'] : '' }}. Once the fix is released, close it in Boogle: <code>php artisan larapilot:boogle-resolve {{ ltrim($item['codes'][0] ?? $item['key'], '#') }}</code>
                                        @elseif ($item['state'] === 'ignored')
                                            Left as it is: {{ $item['reason'] }}
                                        @else
                                            None yet. Run <code>/larapilot-boogle</code> to hand it to triage.
                                        @endif
                                    </p>
                                </div>
                                <dl class="finding-facts">
                                    <div><dt>Exception</dt><dd><code>{{ $item['class'] }}</code></dd></div>
                                    @if ($item['where'] !== null)
                                        <div><dt>Where</dt><dd><code>{{ $item['where'] }}</code>{{ $item['in_vendor'] ? ' — in a package, called by the application' : '' }}</dd></div>
                                    @endif
                                    @if ($item['request'] !== null)
                                        <div><dt>Request</dt><dd><code>{{ $item['request'] }}</code>{{ $item['requests'] > 1 ? ' and '.($item['requests'] - 1).' other '.($item['requests'] === 2 ? 'route' : 'routes') : '' }}</dd></div>
                                    @endif
                                    <div><dt>Thrown</dt><dd>{{ $times($item['count']) }}{{ $item['unseen'] > 0 ? ', '.$item['unseen'].' not opened in Boogle yet' : '' }}</dd></div>
                                    <div><dt>First</dt><dd>{{ $when($item['first_seen']) }}</dd></div>
                                    <div><dt>Last</dt><dd>{{ $when($item['last_seen']) }}</dd></div>
                                    @if ($item['codes'] !== [])
                                        <div><dt>In Boogle</dt><dd>{{ implode(', ', array_slice($item['codes'], 0, 12)) }}{{ count($item['codes']) > 12 ? ' and '.(count($item['codes']) - 12).' more' : '' }}</dd></div>
                                    @endif
                                    <div><dt>Key</dt><dd><code>{{ $item['key'] }}</code></dd></div>
                                </dl>
                            </div>
                        </details>
                    @endforeach
                    <p class="none" id="finding-none" hidden>No error matches.</p>
                </section>

                @if ($read['truncated'])
                    <p class="hint" style="margin: 0">Boogle holds more open occurrences than were read: the counts are of the most recent ones.</p>
                @endif
            @endif

            @if ($outages !== [])
                <section class="card panel">
                    <h3 style="margin: 0 0 4px; font-size: 1rem">Outages</h3>
                    <p class="hint" style="margin: 0 0 10px">Times the uptime monitor of Boogle asked the application and got no answer. An outage is not a bug by itself: it is handed to triage only when asked.</p>
                    <ul class="closed-list">
                        @foreach ($outages as $item)
                            <li>
                                <strong>{{ $times($item['count']) }}</strong>
                                <span>{{ $item['message'] !== '' ? $item['message'] : $item['short'] }}</span>
                                <small>first {{ $when($item['first_seen']) }} · last {{ $when($item['last_seen']) }}{{ $item['codes'] !== [] ? ' · '.implode(', ', array_slice($item['codes'], 0, 4)) : '' }}</small>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($read['closed'] !== [])
                <section class="card panel">
                    <h3 style="margin: 0 0 4px; font-size: 1rem">No longer open in Boogle</h3>
                    <p class="hint" style="margin: 0 0 10px">Errors that were decided about here and that Boogle holds no more as open.</p>
                    <ul class="closed-list">
                        @foreach ($read['closed'] as $closed)
                            <li>
                                <strong>{{ $closed['class'] ?? $closed['key'] }}</strong>
                                @if (($closed['where'] ?? '') !== '')
                                    <code>{{ $closed['where'] }}</code>
                                @endif
                                <small>{{ ($closed['state'] ?? '') === 'ignored' ? 'was left as it was' : 'fixed by '.($closed['spec'] ?? '—') }}</small>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <p class="footer-note" style="margin: 0; text-align: left">
                Decisions are kept in <code>{{ $read['ledger'] }}</code>. The user, the query string, and the payload of a request stay in Boogle: this page shows the exception, the place in the code, and the route. Turn errors into work with <code>/larapilot-boogle</code>.
            </p>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    (() => {
        const filter = document.getElementById('finding-filter');
        const toggles = [...document.querySelectorAll('.toggle[data-state]')];
        const findings = [...document.querySelectorAll('.finding')];
        const none = document.getElementById('finding-none');

        if (findings.length === 0) {
            return;
        }

        let state = '';

        const apply = () => {
            const words = (filter ? filter.value : '').trim().toLowerCase().split(/\s+/).filter(Boolean);
            let shown = 0;

            findings.forEach((finding) => {
                const text = finding.dataset.find || '';
                const inState = state === ''
                    || (state === 'returned' ? finding.dataset.returned === '1' : finding.dataset.state === state);
                const ok = inState && words.every((word) => text.includes(word));

                finding.hidden = !ok;

                if (ok) {
                    shown += 1;
                }
            });

            if (none) {
                none.hidden = shown > 0;
            }
        };

        toggles.forEach((toggle) => {
            toggle.addEventListener('click', () => {
                state = toggle.dataset.state || '';
                toggles.forEach((other) => other.setAttribute('aria-pressed', other === toggle ? 'true' : 'false'));
                apply();
            });
        });

        filter?.addEventListener('input', apply);
    })();
</script>
@endpush
