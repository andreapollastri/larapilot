@extends('larapilot::dashboard.layout')

@section('title', $file === null ? 'Logs' : $file['name'].' · Logs')

@push('styles')
<style>
    .log-get { justify-content: flex-end; gap: 10px 14px; }
    .log-get .hint { margin: 0; }

    .log-check {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--text-2);
        font-size: 0.84rem;
        cursor: pointer;
    }

    .log-check input { width: 16px; height: 16px; margin: 0; accent-color: var(--accent); }

    .log-file { margin: -8px 0 20px; }
    .log-file .chip { max-width: 100%; min-width: 0; }
    .log-file .chip .icon { width: 15px; height: 15px; }
    .log-file code { padding: 0; background: transparent; color: inherit; font-size: 0.78rem; overflow-wrap: anywhere; }

    .log-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 16px;
        align-items: start;
    }

    @media (min-width: 1000px) {
        .log-layout { grid-template-columns: 248px minmax(0, 1fr); gap: 20px; }
    }

    /* ---- files beside the entries ---- */
    .log-side { padding: 0; }

    .log-side > summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 16px;
        color: var(--muted);
        font-size: 0.7rem;
        font-weight: 650;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        cursor: pointer;
        list-style: none;
        user-select: none;
    }

    .log-side > summary::-webkit-details-marker { display: none; }
    .log-side > summary > .icon { width: 15px; height: 15px; transition: transform 0.15s ease; }
    .log-side[open] > summary > .icon { transform: rotate(90deg); }

    @media (min-width: 1000px) {
        .log-side {
            position: sticky;
            top: 24px;
            max-height: calc(100vh - 48px);
            overflow-y: auto;
            scrollbar-width: thin;
        }

        .log-side > summary { cursor: default; pointer-events: none; }
        .log-side > summary > .icon { display: none; }
    }

    .log-side-list { margin: 0; padding: 0 8px 12px; list-style: none; }

    .log-side-list a {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: 1px 8px;
        align-items: center;
        padding: 7px 8px;
        border-radius: var(--radius-xs);
        color: var(--text-2);
        font-size: 0.86rem;
        text-decoration: none;
    }

    .log-side-list a:hover { background: var(--surface-3); text-decoration: none; }
    .log-side-list a .icon { width: 15px; height: 15px; color: var(--muted); }
    .log-side-list a > span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .log-side-list a small { grid-column: 2; color: var(--muted); font-size: 0.74rem; font-variant-numeric: tabular-nums; }
    .log-side-list a[aria-current] { background: var(--accent-soft); color: var(--accent-strong); font-weight: 600; }
    .log-side-list a[aria-current] .icon { color: var(--accent); }
    .log-side-list a[aria-current] small { color: inherit; font-weight: 400; opacity: 0.8; }

    /* ---- what to read ---- */
    .log-tools {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 10px;
        margin-bottom: 14px;
    }

    .log-tools .control { min-width: 0; }
    .log-tools-row { display: flex; gap: 8px; min-width: 0; }
    .log-tools-row > * { flex: 1 1 0; min-width: 0; }
    .log-tools-row > .btn { flex: 0 0 auto; }

    @media (min-width: 760px) {
        .log-tools { grid-template-columns: minmax(0, 1fr) auto; }
        .log-tools-row > select { flex: 0 0 auto; width: auto; }
    }

    .log-tabs { display: flex; gap: 6px; margin-bottom: 14px; flex-wrap: wrap; }

    .log-tabs a {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        min-height: 36px;
        padding: 0 13px;
        border: 1px solid var(--border);
        border-radius: 999px;
        background: var(--surface);
        color: var(--text-2);
        font-size: 0.84rem;
        font-weight: 550;
        text-decoration: none;
    }

    .log-tabs a .icon { width: 15px; height: 15px; color: var(--muted); }
    .log-tabs a:hover { border-color: var(--border-strong); color: var(--text); }

    .log-tabs a[aria-current] {
        border-color: color-mix(in srgb, var(--accent) 45%, var(--border));
        background: var(--accent-soft);
        color: var(--accent-strong);
        font-weight: 600;
    }

    .log-tabs a[aria-current] .icon { color: var(--accent); }

    /* ---- levels ---- */
    .log-tone { --tone: var(--status-todo); }
    .log-emergency, .log-alert, .log-critical, .log-error { --tone: var(--danger-fill); }
    .log-warning { --tone: var(--warn-fill); }
    .log-notice, .log-info { --tone: var(--sky-fill); }

    .log-tally { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }

    .log-tally a {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 4px 11px 4px 9px;
        border: 1px solid var(--border);
        border-radius: 999px;
        background: var(--surface);
        color: var(--text-2);
        font-size: 0.8rem;
        text-decoration: none;
    }

    .log-tally a::before { content: ''; width: 7px; height: 7px; border-radius: 999px; background: var(--tone); }
    .log-tally a.is-all::before { display: none; }
    .log-tally a:hover { border-color: var(--border-strong); color: var(--text); }
    .log-tally a b { font-weight: 650; font-variant-numeric: tabular-nums; }

    .log-tally a[aria-current] {
        border-color: color-mix(in srgb, var(--tone) 55%, var(--border));
        background: color-mix(in srgb, var(--tone) 13%, var(--surface));
        color: var(--text);
        font-weight: 600;
    }

    .log-tally a.is-all[aria-current] {
        border-color: color-mix(in srgb, var(--accent) 45%, var(--border));
        background: var(--accent-soft);
        color: var(--accent-strong);
    }

    .log-note { margin: 0 0 14px; color: var(--muted); font-size: 0.82rem; }

    .log-filters { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }

    .log-filtered {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        max-width: 100%;
        padding: 4px 6px 4px 12px;
        border: 1px solid color-mix(in srgb, var(--accent) 45%, var(--border));
        border-radius: 999px;
        background: var(--accent-soft);
        color: var(--accent-strong);
        font-size: 0.8rem;
    }

    .log-filtered code { padding: 0; background: transparent; color: inherit; overflow-wrap: anywhere; }

    .log-filtered a {
        display: grid;
        place-items: center;
        width: 24px;
        height: 24px;
        flex: none;
        border-radius: 999px;
        color: inherit;
    }

    .log-filtered a:hover { background: color-mix(in srgb, var(--accent) 18%, transparent); }
    .log-filtered a .icon { width: 14px; height: 14px; }

    /* ---- the entries ---- */
    .log-list { overflow: hidden; }

    .log-entry { border-top: 1px solid var(--border); box-shadow: inset 3px 0 0 var(--tone); }
    .log-entry:first-child { border-top: 0; }

    .log-entry > summary {
        display: grid;
        grid-template-columns: auto auto minmax(0, 1fr);
        gap: 6px 10px;
        align-items: baseline;
        padding: 10px 14px 10px 16px;
        cursor: pointer;
        list-style: none;
    }

    .log-entry > summary::-webkit-details-marker { display: none; }
    .log-entry > summary:hover { background: var(--surface-2); }
    .log-entry[open] > summary { background: var(--surface-2); }

    .log-level {
        display: inline-flex;
        align-items: center;
        padding: 2px 8px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--tone) 15%, transparent);
        color: color-mix(in srgb, var(--tone) 54%, var(--text));
        font-size: 0.64rem;
        font-weight: 650;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .log-when { color: var(--muted); font-family: var(--mono); font-size: 0.74rem; font-variant-numeric: tabular-nums; white-space: nowrap; }

    .log-count {
        justify-self: end;
        padding: 1px 8px;
        border-radius: 999px;
        background: var(--surface-3);
        color: var(--text-2);
        font-size: 0.74rem;
        font-weight: 650;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .log-line {
        grid-column: 1 / -1;
        min-width: 0;
        color: var(--text);
        font-size: 0.86rem;
        line-height: 1.5;
        overflow-wrap: anywhere;
    }

    .log-entry:not([open]) .log-msg {
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        overflow: hidden;
    }

    .log-class { font-weight: 650; }
    .log-where { display: inline-block; margin-top: 2px; font-size: 0.74rem; overflow-wrap: anywhere; }
    .log-line mark, .log-detail mark { padding: 0 1px; border-radius: 3px; background: color-mix(in srgb, var(--warn-fill) 38%, transparent); color: inherit; }

    @media (min-width: 760px) {
        .log-entry > summary { grid-template-columns: 82px auto minmax(0, 1fr) auto; }
        .log-line { grid-column: 3; }
        .log-count { grid-column: 4; grid-row: 1; }
    }

    .log-detail { display: grid; gap: 14px; padding: 4px 14px 16px 16px; background: var(--surface-2); min-width: 0; }

    .log-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 16px;
        margin: 0;
        padding: 0;
        list-style: none;
        color: var(--muted);
        font-size: 0.78rem;
        font-variant-numeric: tabular-nums;
    }

    .log-detail h4 {
        margin: 0 0 6px;
        color: var(--muted);
        font-size: 0.68rem;
        font-weight: 650;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .log-thrown { margin: 0; min-width: 0; }
    .log-thrown dt { color: var(--text); font-family: var(--mono); font-size: 0.8rem; font-weight: 600; overflow-wrap: anywhere; }
    .log-thrown dd { margin: 3px 0 0; color: var(--text-2); font-size: 0.86rem; white-space: pre-wrap; overflow-wrap: anywhere; }
    .log-thrown dd + dt { margin-top: 12px; }
    .log-thrown small { display: block; margin-top: 3px; color: var(--muted); font-family: var(--mono); font-size: 0.74rem; overflow-wrap: anywhere; }

    .log-frames { margin: 0; padding: 0; list-style: none; border: 1px solid var(--border); border-radius: var(--radius-xs); background: var(--surface); overflow: hidden; }

    /* each row sits a pixel over the one above: the first one shown never draws a line under the frame of the list */
    .log-frames li {
        display: grid;
        grid-template-columns: 30px minmax(0, 1fr);
        gap: 0 8px;
        margin-top: -1px;
        padding: 6px 10px;
        border-top: 1px solid var(--border);
        font-family: var(--mono);
        font-size: 0.74rem;
        line-height: 1.5;
    }

    .log-frames li > span:first-child { color: var(--muted); font-variant-numeric: tabular-nums; text-align: right; }
    .log-frames li > span:last-child { min-width: 0; overflow-wrap: anywhere; }
    .log-frames li small { display: block; color: var(--muted); font-size: inherit; }
    .log-frames .is-app { background: color-mix(in srgb, var(--accent) 7%, var(--surface)); }
    .log-frames .is-app > span:last-child { color: var(--text); font-weight: 600; }
    .log-frames .is-vendor > span:last-child { color: var(--muted); }
    .log-frames.is-folded .is-vendor { display: none; }

    .log-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }

    .log-pre {
        margin: 0;
        padding: 10px 12px;
        border: 1px solid var(--border);
        border-radius: var(--radius-xs);
        background: var(--surface);
        font-size: 0.76rem;
        line-height: 1.55;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
        max-height: 420px;
        overflow-y: auto;
        scrollbar-width: thin;
    }

    .log-raw > summary { color: var(--accent); font-size: 0.82rem; cursor: pointer; }
    .log-raw[open] > summary { margin-bottom: 8px; }

    .log-pager {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px 16px;
        padding: 10px 16px;
        border-top: 1px solid var(--border);
        background: var(--surface-2);
        color: var(--muted);
        font-size: 0.8rem;
        font-variant-numeric: tabular-nums;
    }

    .log-pager nav { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
    .log-pager .btn .icon { width: 15px; height: 15px; }
</style>
@endpush

@section('content')
    <header class="page-head">
        <div>
            <h2>Logs</h2>
            <p class="sub">What the application wrote to <code>{{ $directory }}</code>, read as entries: the newest first, by level, searched, the repeats counted — an exception with where it was thrown and the frames of your own code told apart. Passwords, tokens, and keys are shown as <code>[REDACTED]</code>. Nothing is changed from here.</p>
        </div>
        @if ($file !== null)
            <form class="page-actions log-get" method="get" action="{{ $link() }}">
                <input type="hidden" name="download" value="1">
                @if ($secrets_allowed)
                    <label class="log-check" title="Passwords, tokens, and keys are redacted unless this is ticked.">
                        <input type="checkbox" name="secrets" value="1">
                        Secrets as written
                    </label>
                @else
                    <span class="hint">Secrets are redacted on a shared host.</span>
                @endif
                <button type="submit" class="btn">@include('larapilot::dashboard.partials.icon', ['name' => 'download'])Download log</button>
            </form>
        @endif
    </header>

    @if ($file === null)
        <section class="card empty">
            <p>No log yet. <code>{{ $directory }}</code> holds no <code>.log</code> file — the application writes one the first time it logs. <code>LARAPILOT_LOG_VIEWER_PATH</code> points the page at another folder.</p>
        </section>
    @else
        @php
            $laravel = $overview['format'] === 'laravel';
            $levels = \Larapilot\Services\LogViewerService::LEVELS;
            $sinces = \Larapilot\Services\LogViewerService::SINCE;
            $level = $laravel ? $filters['level'] : null;
            $since = $laravel ? $filters['since'] : null;
            $search = $filters['search'];
            $terms = $filters['marks'];
            $state = array_filter([
                'q' => $search !== '' ? $search : null,
                'level' => $level,
                'since' => $since,
                'view' => $grouped ? 'groups' : null,
            ], static fn ($value): bool => $value !== null);
            $to = static fn (array $changes = []): string => $link(array_merge($state, $changes));
            $filtered = $search !== '' || $level !== null || $since !== null;
            $rows = $grouped ? $groups : $entries;

            // The words of the search, marked where the entry shows them.
            $mark = static function (string $text) use ($terms): \Illuminate\Support\HtmlString {
                if ($terms === [] || $text === '') {
                    return new \Illuminate\Support\HtmlString(e($text));
                }

                $pattern = '/('.implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $terms)).')/iu';
                $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);

                if ($parts === false) {
                    return new \Illuminate\Support\HtmlString(e($text));
                }

                $html = '';

                foreach ($parts as $index => $part) {
                    $html .= $index % 2 === 1 ? '<mark>'.e($part).'</mark>' : e($part);
                }

                return new \Illuminate\Support\HtmlString($html);
            };
        @endphp

        <div class="chips log-file" aria-label="File">
            <span class="chip current">@include('larapilot::dashboard.partials.icon', ['name' => 'logs'])<code>{{ $file['key'] }}</code></span>
            <span class="chip">{{ $file['size_label'] }}</span>
            <span class="chip">Written {{ $file['modified_label'] }}</span>
            @if ($file['current'])
                <span class="chip live">The application writes here</span>
            @endif
        </div>

        <div class="log-layout">
            <details class="card log-side" open data-log-side>
                <summary>Log files @include('larapilot::dashboard.partials.icon', ['name' => 'chevron'])</summary>
                <ul class="log-side-list">
                    @foreach ($files as $item)
                        <li>
                            <a href="{{ $open($item['key']) }}" @if ($item['key'] === $file['key']) aria-current="page" @endif title="{{ $item['key'] }}">
                                @include('larapilot::dashboard.partials.icon', ['name' => 'file'])
                                <span>{{ $item['key'] }}</span>
                                <small>{{ $item['size_label'] }} · {{ $item['modified_label'] }}</small>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </details>

            <div>
                <form class="log-tools" method="get" action="{{ $link() }}" role="search">
                    @if ($grouped)
                        <input type="hidden" name="view" value="groups">
                    @endif
                    <input class="control" type="search" name="q" value="{{ $search }}" placeholder="Search: words, or a &quot;phrase&quot;" title="Every word must be in the entry; quotes keep several together" aria-label="Search the entries">
                    <div class="log-tools-row">
                        @if ($laravel)
                            <select class="control" name="level" aria-label="Level">
                                <option value="">Every level</option>
                                @foreach (array_reverse($levels) as $name)
                                    @continue($name === 'debug')
                                    <option value="{{ $name }}" @selected($level === $name)>{{ ucfirst($name) }}{{ $name === 'emergency' ? '' : ' and worse' }}</option>
                                @endforeach
                            </select>
                            <select class="control" name="since" aria-label="Period">
                                <option value="">Any time</option>
                                @foreach ($sinces as $value => $label)
                                    <option value="{{ $value }}" @selected($since === $value)>{{ $label }}</option>
                                @endforeach
                                @if ($since !== null && ! isset($sinces[$since]))
                                    <option value="{{ $since }}" selected>Since {{ $since }}</option>
                                @endif
                            </select>
                        @endif
                        <button type="submit" class="btn ghost">@include('larapilot::dashboard.partials.icon', ['name' => 'search'])Search</button>
                    </div>
                </form>

                <nav class="log-tabs" aria-label="View">
                    <a href="{{ $to(['view' => null]) }}" @if (! $grouped) aria-current="page" @endif>@include('larapilot::dashboard.partials.icon', ['name' => 'logs'])Entries</a>
                    <a href="{{ $to(['view' => 'groups']) }}" @if ($grouped) aria-current="page" @endif>@include('larapilot::dashboard.partials.icon', ['name' => 'layers'])Repeats counted</a>
                </nav>

                @if ($laravel && $overview['levels'] !== [])
                    <nav class="log-tally" aria-label="Levels">
                        <a class="is-all" href="{{ $to(['level' => null]) }}" @if ($level === null) aria-current="true" @endif>All <b>{{ number_format($overview['entries']) }}</b></a>
                        @foreach ($levels as $name)
                            @if (isset($overview['levels'][$name]))
                                <a class="log-tone log-{{ $name }}" href="{{ $to(['level' => $name]) }}" title="{{ ucfirst($name) }}{{ $name === 'emergency' ? '' : ' and worse' }}" @if ($level === $name) aria-current="true" @endif>{{ ucfirst($name) }} <b>{{ number_format($overview['levels'][$name]) }}</b></a>
                            @endif
                        @endforeach
                    </nav>
                @endif

                <p class="log-note">
                    @if (! $laravel)
                        This file is not in Laravel's log format: it is read a line at a time, without levels or dates.
                    @elseif ($overview['from'] !== null)
                        {{ number_format($overview['entries']) }} {{ $overview['entries'] === 1 ? 'entry' : 'entries' }} from {{ str_replace('T', ' ', $overview['from']) }} to {{ str_replace('T', ' ', (string) $overview['to']) }}@if (! $overview['complete']), in the last {{ $overview['scanned_label'] }} of the file — the counts are of that part @endif.
                    @endif
                </p>

                @if ($filtered)
                    <div class="log-filters">
                        @if ($search !== '')
                            <span class="log-filtered">Text has <code>{{ $search }}</code><a href="{{ $to(['q' => null]) }}" aria-label="Clear the search">@include('larapilot::dashboard.partials.icon', ['name' => 'close'])</a></span>
                        @endif
                        @if ($level !== null)
                            <span class="log-filtered">{{ ucfirst($level) }}{{ $level === 'emergency' ? '' : ' and worse' }}<a href="{{ $to(['level' => null]) }}" aria-label="Every level">@include('larapilot::dashboard.partials.icon', ['name' => 'close'])</a></span>
                        @endif
                        @if ($since !== null)
                            <span class="log-filtered">{{ $sinces[$since] ?? 'Since '.$since }}<a href="{{ $to(['since' => null]) }}" aria-label="Any time">@include('larapilot::dashboard.partials.icon', ['name' => 'close'])</a></span>
                        @endif
                    </div>
                @endif

                <section class="card log-list" aria-label="{{ $grouped ? 'What was logged, the repeats counted' : 'Entries of '.$file['name'] }}">
                    @if ($rows === [])
                        <div class="empty">
                            <p>
                                @if ($filtered)
                                    No entry matches.
                                @elseif ($before > 0)
                                    Nothing older.
                                @else
                                    This log is empty.
                                @endif
                            </p>
                        </div>
                    @else
                        @foreach ($rows as $entry)
                            @php
                                $tone = $entry['level'] === '' ? 'plain' : $entry['level'];
                                $thrown = $entry['exception'];
                                $clock = $entry['time'] === '' ? '' : substr($entry['time'], 11);
                                $day = $entry['time'] === '' ? '' : substr($entry['time'], 0, 10);
                                $folded = $entry['frames_app'] > 0 && $entry['frames_app'] < count($entry['frames']);

                                // What finds the other times it was logged: the class of the
                                // exception, or the start of the message up to what changes.
                                $phrase = $thrown !== null
                                    ? $thrown['short']
                                    : (preg_match('/^[^\d"\']{8,60}/u', $entry['message'], $start) === 1 ? trim($start[0]) : null);
                            @endphp
                            <details class="log-entry log-tone log-{{ $tone }}" id="entry-{{ $entry['offset'] }}">
                                <summary>
                                    @if ($entry['level'] !== '')
                                        <span class="log-level">{{ $entry['level'] }}</span>
                                    @else
                                        <span class="log-level">Line</span>
                                    @endif
                                    <span class="log-when" @if ($entry['ago'] !== null) title="{{ $entry['ago'] }}" @endif>{{ $day }} {{ $clock }}</span>
                                    @if ($grouped)
                                        <span class="log-count" title="Logged {{ number_format($entry['count']) }} {{ $entry['count'] === 1 ? 'time' : 'times' }}">×{{ number_format($entry['count']) }}</span>
                                    @endif
                                    <span class="log-line">
                                        <span class="log-msg">@if ($thrown !== null)<span class="log-class">{{ $mark($thrown['short']) }}</span> · @endif{{ $mark($entry['message']) }}</span>
                                        @if ($entry['where'] !== null)
                                            <code class="log-where">{{ $mark($entry['where']) }}</code>
                                        @endif
                                    </span>
                                </summary>
                                <div class="log-detail">
                                    <ul class="log-meta">
                                        @if ($entry['time'] !== '')
                                            <li>{{ $entry['time'] }}{{ $entry['zone'] }}@if ($entry['ago'] !== null) · {{ $entry['ago'] }}@endif</li>
                                        @endif
                                        @if ($grouped && $entry['count'] > 1)
                                            <li>First {{ $entry['first'] }}</li>
                                        @endif
                                        @if ($entry['env'] !== '')
                                            <li>Environment {{ $entry['env'] }}</li>
                                        @endif
                                        <li>{{ $entry['bytes_label'] }}@if ($entry['truncated']) — the start is shown @endif</li>
                                    </ul>

                                    @if ($thrown !== null)
                                        <div>
                                            <h4>Exception</h4>
                                            <dl class="log-thrown">
                                                @foreach (array_merge([$thrown], $entry['previous']) as $index => $cause)
                                                    <dt>{{ $index > 0 ? 'Caused by ' : '' }}{{ $mark($cause['class']) }}</dt>
                                                    <dd>{{ $mark($cause['message']) }}<small>@if ($cause['url'] !== null)<a href="{{ $cause['url'] }}">{{ $cause['where'] }}</a>@else{{ $cause['where'] }}@endif</small></dd>
                                                @endforeach
                                            </dl>
                                        </div>
                                    @endif

                                    @if ($entry['frames'] !== [])
                                        <div>
                                            <h4>Stack — {{ number_format($entry['frames_total']) }} {{ $entry['frames_total'] === 1 ? 'frame' : 'frames' }}@if ($entry['frames_app'] > 0), {{ $entry['frames_app'] }} in the application @endif</h4>
                                            <ol class="log-frames" @if ($folded) data-log-frames @endif>
                                                @foreach ($entry['frames'] as $frame)
                                                    <li class="{{ $frame['app'] ? 'is-app' : 'is-vendor' }}">
                                                        <span>#{{ $frame['index'] }}</span>
                                                        <span>
                                                            @if ($frame['file'] !== '')
                                                                @if ($frame['url'] !== null)<a href="{{ $frame['url'] }}">{{ $frame['file'] }}:{{ $frame['line'] }}</a>@else{{ $frame['file'] }}:{{ $frame['line'] }}@endif
                                                                <small>{{ $frame['call'] }}</small>
                                                            @else
                                                                {{ $frame['call'] }}
                                                            @endif
                                                        </span>
                                                    </li>
                                                @endforeach
                                            </ol>
                                            @if ($folded)
                                                <div class="log-actions">
                                                    <button type="button" class="btn ghost small" data-log-unfold hidden>Show the {{ count($entry['frames']) - $entry['frames_app'] }} frames of the framework and packages</button>
                                                </div>
                                            @endif
                                        </div>
                                    @endif

                                    @if ($entry['context'] !== null)
                                        <div>
                                            <h4>Context</h4>
                                            <pre class="log-pre">{{ $mark($entry['context']) }}</pre>
                                        </div>
                                    @endif

                                    @if ($thrown !== null || $entry['context'] !== null)
                                        <details class="log-raw">
                                            <summary>As it was written</summary>
                                            <pre class="log-pre" data-log-body>{{ $entry['body'] }}</pre>
                                        </details>
                                    @else
                                        <pre class="log-pre" data-log-body>{{ $mark($entry['body']) }}</pre>
                                    @endif

                                    <div class="log-actions">
                                        <button type="button" class="btn ghost small" data-log-copy hidden>@include('larapilot::dashboard.partials.icon', ['name' => 'copy'])Copy the entry</button>
                                        @if ($grouped && $entry['count'] > 1 && $phrase !== null && $phrase !== '')
                                            <a class="btn ghost small" href="{{ $link(['q' => '"'.$phrase.'"', 'level' => $entry['level'], 'since' => $since]) }}">@include('larapilot::dashboard.partials.icon', ['name' => 'logs'])Every time it was logged</a>
                                        @endif
                                    </div>
                                </div>
                            </details>
                        @endforeach
                    @endif

                    <div class="log-pager">
                        <span>
                            @if ($grouped)
                                {{ number_format(count($rows)) }} of {{ $capped ? 'more than ' : '' }}{{ number_format($total) }} {{ $total === 1 ? 'thing' : 'things' }} logged, in {{ number_format($entries) }} {{ $entries === 1 ? 'entry' : 'entries' }} — the most repeated first{{ $complete ? '' : ' · the last '.$scan_label.' of the file' }}{{ $capped ? ' · the repeats are counted of the first '.number_format($total).' met from the end of the file' : '' }}
                            @else
                                {{ number_format(count($rows)) }} {{ count($rows) === 1 ? 'entry' : 'entries' }}{{ $filtered ? ' found' : '' }} — the newest first
                            @endif
                        </span>
                        @if (! $grouped && ($before > 0 || $next !== null || $resume !== null))
                            <nav aria-label="Pages">
                                @if ($before > 0)
                                    <a class="btn ghost small" href="{{ $to() }}">@include('larapilot::dashboard.partials.icon', ['name' => 'chevron-left'])Newest</a>
                                @endif
                                @if ($next !== null)
                                    <a class="btn ghost small" href="{{ $to(['before' => $next]) }}">Older @include('larapilot::dashboard.partials.icon', ['name' => 'chevron'])</a>
                                @elseif ($resume !== null)
                                    <span>{{ $scan_label }} read.</span>
                                    <a class="btn ghost small" href="{{ $to(['before' => $resume]) }}">Keep reading older @include('larapilot::dashboard.partials.icon', ['name' => 'chevron'])</a>
                                @endif
                            </nav>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
    (() => {
        // On a phone the list of files starts folded, so the entries come first.
        document.querySelectorAll('[data-log-side]').forEach((side) => {
            const wide = window.matchMedia('(min-width: 1000px)');
            const sync = () => {
                side.open = wide.matches;
            };

            sync();
            wide.addEventListener?.('change', sync);
        });

        // A stack opens on the frames of the application; the rest is one click away.
        document.querySelectorAll('[data-log-frames]').forEach((frames) => {
            const button = frames.parentElement.querySelector('[data-log-unfold]');

            if (!button) {
                return;
            }

            const more = button.textContent;

            frames.classList.add('is-folded');
            button.hidden = false;

            button.addEventListener('click', () => {
                const folded = frames.classList.toggle('is-folded');

                button.textContent = folded ? more : 'Show the frames of the application only';
            });
        });

        if (!navigator.clipboard) {
            return;
        }

        document.querySelectorAll('[data-log-copy]').forEach((button) => {
            const label = button.innerHTML;

            button.hidden = false;

            button.addEventListener('click', async () => {
                const body = button.closest('.log-detail').querySelector('[data-log-body]');

                try {
                    await navigator.clipboard.writeText(body ? body.textContent : '');
                    button.textContent = 'Copied';
                } catch (error) {
                    button.textContent = 'Copy failed';
                }

                setTimeout(() => {
                    button.innerHTML = label;
                }, 1600);
            });
        });
    })();
</script>
@endpush
