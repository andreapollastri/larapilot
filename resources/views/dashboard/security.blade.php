@extends('larapilot::dashboard.layout')

@section('title', 'Security')

@push('styles')
<style>
    .security-page { display: flex; flex-direction: column; gap: 20px; }
    .security-page .page-head, .security-page .metrics { margin-bottom: 0; }
    /* Three actions: they go under the words before the words are squeezed. */
    .security-page .page-head > :first-child { flex-basis: 520px; }

    /* Severity is a state, so it is always a word beside the colour. */
    .sev { --tone: var(--status-todo); }
    .sev-critical { --tone: var(--danger-fill); }
    .sev-high { --tone: #ec835a; }
    .sev-medium { --tone: var(--warn-fill); }
    .sev-low { --tone: var(--status-todo); }

    .sev-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex: none;
        min-width: 84px;
        padding: 3px 10px 3px 9px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--tone) 16%, transparent);
        color: color-mix(in srgb, var(--tone) 52%, var(--text));
        font-size: 0.7rem;
        font-weight: 650;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .sev-chip::before { content: ''; width: 7px; height: 7px; border-radius: 999px; background: var(--tone); }

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
    .verdict .icon { flex: none; width: 22px; height: 22px; margin-top: 1px; }
    .verdict.is-pass .icon { color: var(--ok); }
    .verdict.is-warn .icon { color: var(--warn); }
    .verdict.is-fail .icon { color: var(--danger); }
    .verdict strong { display: block; font-size: 1.02rem; font-weight: 650; }
    .verdict p { margin: 3px 0 0; color: var(--text-2); font-size: 0.9rem; line-height: 1.5; max-width: 84ch; }

    .metric .sev-chip { margin-bottom: 8px; }
    .metric-value { font-variant-numeric: tabular-nums; }

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

    .finding-title { min-width: 0; }
    .finding-title strong { display: block; font-size: 0.95rem; font-weight: 600; overflow-wrap: anywhere; }
    .finding-title small { display: block; margin-top: 2px; color: var(--muted); font-size: 0.78rem; line-height: 1.4; }

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
    .state.is-waived { color: var(--muted); }

    @media (max-width: 639px) {
        .finding > summary { grid-template-columns: minmax(0, 1fr) auto; }
        .finding > summary .sev-chip { justify-self: start; }
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
        .finding-body { grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); }
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
    .finding-facts { margin: 0; font-size: 0.86rem; }
    .finding-facts div { display: grid; grid-template-columns: 108px minmax(0, 1fr); gap: 10px; padding: 7px 0; border-top: 1px solid var(--border); }
    .finding-facts div:first-child { border-top: 0; padding-top: 0; }
    .finding-facts dt { color: var(--muted); }
    .finding-facts dd { margin: 0; overflow-wrap: anywhere; }

    .none { padding: 30px 20px; text-align: center; color: var(--muted); }
    .none[hidden] { display: none; }

    .closed-list { margin: 0; padding: 0; list-style: none; font-size: 0.88rem; }
    .closed-list li { display: flex; flex-wrap: wrap; gap: 4px 10px; padding: 8px 0; border-top: 1px solid var(--border); }
    .closed-list li:first-child { border-top: 0; }
    .closed-list small { color: var(--muted); }

    .repo-choice { display: flex; align-items: end; gap: 10px 12px; flex-wrap: wrap; }
    .repo-choice .field { flex: 1 1 280px; max-width: 520px; }
</style>
@endpush

@section('content')
    @php
        $findings = is_array($findings ?? null) ? $findings : null;
        $issues = $findings['issues'] ?? [];
        $gate = $findings['gate'] ?? null;
        $repository = $findings['repository'] ?? null;
        $when = static fn (?string $iso): string => $iso ? \Illuminate\Support\Carbon::parse($iso)->format('M j, Y · H:i') : '—';
        $day = static fn (?string $iso): string => $iso ? \Illuminate\Support\Carbon::parse($iso)->format('M j, Y') : '—';
        $stateLabel = ['new' => 'No decision yet', 'in_backlog' => 'In the backlog', 'waived' => 'Waived'];
        $verdictWords = [
            'PASS' => 'Nothing stops a release',
            'WARN' => 'Nothing stops a release, and there is something to look at',
            'FAIL' => 'A release is stopped',
        ];
        $verdictIcon = ['PASS' => 'check', 'WARN' => 'info', 'FAIL' => 'info'];
    @endphp

    <div class="security-page">
        <header class="page-head">
            <div>
                <h2>Security</h2>
                @if ($enabled)
                    <p class="sub">What Aikido found in this repository, and what was decided about each finding. Aikido scans on its side; this page reads the result.</p>
                @else
                    <p class="sub">Aikido scans this repository for vulnerable dependencies, weaknesses in the code, leaked secrets, and risky configuration. Larapilot reads what it found; it runs no scanner of its own.</p>
                    <p class="sub">Connect the repository in <a href="https://www.aikido.dev/">Aikido</a>, put the API client in <code>.env</code>, then turn the link on: <code>php artisan larapilot:settings-set --aikido=YES</code>.</p>
                @endif
                @if ($repository)
                    <div class="chips" style="margin-top: 12px">
                        <span class="chip current">{{ $repository['name'] }}</span>
                        @if ($repository['branch'] !== '')
                            <span class="chip">{{ $repository['branch'] }}</span>
                        @endif
                        <span class="chip">Scanned {{ $when($repository['last_scanned_at']) }}</span>
                        <span class="chip">Read {{ $when($findings['fetched_at']) }}</span>
                    </div>
                @endif
            </div>
            @if ($enabled)
                <div class="page-actions">
                    <a class="btn ghost" href="{{ route('larapilot.dashboard.security', ['refresh' => 1]) }}" title="Ask Aikido again instead of showing what was read a few minutes ago">@include('larapilot::dashboard.partials.icon', ['name' => 'refresh'])Read again</a>
                    @if ($findings)
                        <a class="btn ghost" href="{{ route('larapilot.dashboard.security.report') }}" title="The open findings in detail, for the team">@include('larapilot::dashboard.partials.icon', ['name' => 'download'])Download report (.md)</a>
                        <a class="btn ghost" href="{{ route('larapilot.dashboard.security.register') }}" title="Every finding that is open, resolved, or ignored with its reason — the document a client or an auditor asks for">@include('larapilot::dashboard.partials.icon', ['name' => 'download'])Register for the client (.md)</a>
                    @endif
                </div>
            @endif
        </header>

        @if ($enabled && $error)
            <div class="flash flash--error" role="alert">
                <strong>{{ $error }}</strong>
                {{-- With the list under it, the page asks: the commands are said once, beside the form. --}}
                @if ($hint && empty($repositories['repositories']))
                    <span>{{ $hint }}</span>
                @endif
            </div>
            @if (! empty($repositories['repositories']))
                <section class="card panel">
                    <h3 style="margin: 0 0 4px; font-size: 1rem">Which repository of Aikido is this project?</h3>
                    <p class="hint" style="margin: 0 0 12px">The git remote of this project{{ ($repositories['origin'] ?? null) ? ' ('.$repositories['origin'].')' : '' }} matches none of the repositories Aikido scans. Choose the one that holds this code: the findings are read from it.</p>
                    <form class="repo-choice" method="post" action="{{ route('larapilot.dashboard.security.repository') }}">
                        @csrf
                        <label class="field">
                            Repository in Aikido
                            <select class="control" name="repository" required>
                                <option value="" selected disabled>Choose a repository…</option>
                                @foreach ($repositories['repositories'] as $candidate)
                                    <option value="{{ $candidate['id'] }}">{{ $candidate['name'] }}{{ $candidate['branch'] !== '' ? ' · '.$candidate['branch'] : '' }}{{ $candidate['provider'] !== '' ? ' · '.$candidate['provider'] : '' }} (#{{ $candidate['id'] }})</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="submit" class="btn">Use this repository</button>
                    </form>
                    <p class="hint" style="margin: 12px 0 0">
                        @if ($repositories['truncated'])
                            The workspace holds more repositories than this list shows: name yours from the terminal, <code>php artisan larapilot:aikido-repos --search=name</code> then <code>--use=id</code>.
                        @else
                            A repository that is not in the list has to be connected in Aikido first. The choice is kept in <code>{{ $repositories['ledger'] }}</code>, for every machine; from the terminal it is <code>php artisan larapilot:aikido-repos --use=id</code>.
                        @endif
                    </p>
                </section>
            @endif
            @if (! empty($status['hints']) && empty($repositories['repositories']))
                <section class="card panel">
                    <h3 style="margin: 0 0 8px; font-size: 1rem">What to check</h3>
                    <ul class="notes" style="margin: 0; padding-left: 1.1rem; color: var(--text-2); font-size: 0.9rem; line-height: 1.6">
                        @foreach ($status['hints'] as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                    <p class="hint" style="margin: 12px 0 0">Check from the terminal: <code>php artisan larapilot:aikido-status</code></p>
                </section>
            @endif
        @elseif ($enabled)
            <div @class(['verdict', 'is-'.strtolower($gate['verdict'])]) role="status">
                @include('larapilot::dashboard.partials.icon', ['name' => $verdictIcon[$gate['verdict']] ?? 'info'])
                <div>
                    <strong>{{ $verdictWords[$gate['verdict']] ?? $gate['verdict'] }}</strong>
                    <p>{{ $gate['summary'] }}
                        @if ($gate['fail_on'] !== 'none')
                            The ship gate stops on an open finding that is <strong style="display: inline; font-size: inherit">{{ $gate['fail_on'] }}</strong> or above and was not waived.
                        @else
                            The ship gate is set to stop on nothing.
                        @endif
                        A finding in the backlog is not fixed yet: it counts until Aikido no longer reports it.
                    </p>
                </div>
            </div>

            <section class="metrics" aria-label="Open findings by severity">
                @foreach (\Larapilot\Services\AikidoService::SEVERITIES as $severity)
                    <div class="card metric sev sev-{{ $severity }}">
                        <span class="sev-chip">{{ $severity }}</span>
                        <div class="metric-value">{{ number_format($findings['counts'][$severity]) }}</div>
                        <div class="metric-note">open {{ $findings['counts'][$severity] === 1 ? 'finding' : 'findings' }}</div>
                    </div>
                @endforeach
            </section>

            @if ($issues === [])
                <div class="card empty">
                    <p>Aikido reports nothing open for <strong>{{ $repository['name'] }}</strong>.</p>
                </div>
            @else
                <div class="tools">
                    <label class="field">
                        Find a finding
                        <input type="search" id="finding-filter" placeholder="A package, a CVE, a word…" autocomplete="off">
                    </label>
                    <div class="toggles" role="group" aria-label="Show by decision">
                        <button type="button" class="toggle" data-state="" aria-pressed="true">All <b>{{ $findings['total'] }}</b></button>
                        <button type="button" class="toggle" data-state="new" aria-pressed="false">No decision yet <b>{{ $findings['states']['new'] }}</b></button>
                        <button type="button" class="toggle" data-state="in_backlog" aria-pressed="false">In the backlog <b>{{ $findings['states']['in_backlog'] }}</b></button>
                        <button type="button" class="toggle" data-state="waived" aria-pressed="false">Waived <b>{{ $findings['states']['waived'] }}</b></button>
                    </div>
                </div>

                <section class="card findings" aria-label="Open findings, the most severe first">
                    @foreach ($issues as $issue)
                        <details class="finding sev sev-{{ $issue['severity'] }}" data-state="{{ $issue['state'] }}" data-find="{{ strtolower($issue['title'].' '.$issue['type_label'].' '.implode(' ', $issue['cves']).' '.implode(' ', $issue['where']).' '.($issue['spec'] ?? '')) }}">
                            <summary>
                                <span class="sev-chip">{{ $issue['severity'] }}</span>
                                <span class="finding-title">
                                    <strong>{{ $issue['title'] }}</strong>
                                    <small>#{{ $issue['id'] }} · {{ $issue['type_label'] }}{{ $issue['cves'] !== [] ? ' · '.implode(', ', array_slice($issue['cves'], 0, 3)).(count($issue['cves']) > 3 ? ' +'.(count($issue['cves']) - 3) : '') : '' }}</small>
                                </span>
                                <span class="state is-{{ $issue['state'] }}">{{ $issue['state'] === 'in_backlog' ? $issue['spec'] : $stateLabel[$issue['state']] }}</span>
                            </summary>
                            <div class="finding-body">
                                <div>
                                    @if ($issue['description'] !== '')
                                        <h4>What it is</h4>
                                        <p>{{ $issue['description'] }}</p>
                                    @endif
                                    @if ($issue['how_to_fix'] !== '')
                                        <h4>How to fix</h4>
                                        <p>{{ $issue['how_to_fix'] }}</p>
                                    @endif
                                    <h4>Decision</h4>
                                    <p>
                                        @if ($issue['state'] === 'in_backlog')
                                            In the backlog as <a href="{{ $issue['spec_url'] }}">{{ $issue['spec'] }}</a>{{ $issue['spec_status'] ? ', now '.$issue['spec_status'] : '' }}. It stays here until Aikido no longer reports it.
                                        @elseif ($issue['state'] === 'waived')
                                            Waived: {{ $issue['reason'] }}
                                        @else
                                            None yet. Run <code>/larapilot-aikido</code> to hand it to triage.
                                        @endif
                                    </p>
                                </div>
                                <dl class="finding-facts">
                                    <div><dt>Severity</dt><dd>{{ ucfirst($issue['severity']) }}{{ $issue['score'] > 0 ? ' · '.$issue['score'].' out of 100' : '' }}</dd></div>
                                    <div><dt>Kind</dt><dd>{{ $issue['type_label'] }}</dd></div>
                                    @if ($issue['where'] !== [])
                                        <div><dt>Where</dt><dd>{{ implode(', ', $issue['where']) }}</dd></div>
                                    @endif
                                    @if ($issue['cves'] !== [])
                                        <div>
                                            <dt>CVE</dt>
                                            <dd>
                                                @foreach ($issue['cves'] as $cve)
                                                    @if (preg_match('/^CVE-\d{4}-\d+$/', $cve) === 1)
                                                        <a href="https://www.cve.org/CVERecord?id={{ $cve }}" target="_blank" rel="noopener noreferrer">{{ $cve }}</a>{{ $loop->last ? '' : ', ' }}
                                                    @else
                                                        {{ $cve }}{{ $loop->last ? '' : ', ' }}
                                                    @endif
                                                @endforeach
                                            </dd>
                                        </div>
                                    @endif
                                    <div><dt>First seen</dt><dd>{{ $day($issue['first_detected_at']) }}</dd></div>
                                    @if ($issue['minutes_to_fix'] > 0)
                                        <div><dt>Time to fix</dt><dd>About {{ $issue['minutes_to_fix'] >= 120 ? round($issue['minutes_to_fix'] / 60, 1).' hours' : $issue['minutes_to_fix'].' minutes' }}, by Aikido's estimate</dd></div>
                                    @endif
                                </dl>
                            </div>
                        </details>
                    @endforeach
                    <p class="none" id="finding-none" hidden>No finding matches.</p>
                </section>

                @if ($findings['truncated'])
                    <p class="hint" style="margin: 0">Only the first {{ number_format(count($issues)) }} findings are shown. Aikido holds the rest.</p>
                @endif
            @endif

            @if ($findings['closed'] !== [])
                <section class="card panel">
                    <h3 style="margin: 0 0 4px; font-size: 1rem">No longer open in Aikido</h3>
                    <p class="hint" style="margin: 0 0 10px">Findings that were decided about here and that Aikido reports as open no more: fixed, or waived here and ignored there.</p>
                    <ul class="closed-list">
                        @foreach ($findings['closed'] as $closed)
                            <li>
                                <strong>#{{ $closed['id'] }} {{ $closed['title'] ?? '' }}</strong>
                                <small>{{ ($closed['state'] ?? '') === 'waived' ? 'waived: '.($closed['reason'] ?? '—') : 'fixed by '.($closed['spec'] ?? '—') }}</small>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($findings['unsent'] !== [])
                <p class="hint" style="margin: 0">{{ count($findings['unsent']) === 1 ? 'One decision was' : count($findings['unsent']).' decisions were' }} not told to Aikido yet (#{{ implode(', #', $findings['unsent']) }}). Tell Aikido with <code>php artisan larapilot:aikido-push</code>; the credentials need the <code>issues:write</code> scope.</p>
            @endif

            <p class="footer-note" style="margin: 0; text-align: left">
                Decisions are kept in <code>{{ $findings['ledger'] }}</code>{{ $findings['push_decisions'] ? ' and told to Aikido: a waiver ignores the finding there, with its reason' : '' }}. Turn findings into work with <code>/larapilot-aikido</code>; ask for a new scan with <code>php artisan larapilot:aikido-scan</code>.
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
                const ok = (state === '' || finding.dataset.state === state) && words.every((word) => text.includes(word));

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
