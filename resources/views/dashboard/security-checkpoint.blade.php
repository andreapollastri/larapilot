@extends('larapilot::dashboard.layout')

@section('title', 'Security · Checkpoint')

@push('styles')
<style>
    .checkpoint-page { display: flex; flex-direction: column; gap: 20px; }
    .checkpoint-page .page-head, .checkpoint-page .metrics { margin-bottom: 0; }
    .checkpoint-page .page-head > :first-child { flex-basis: 520px; }
    .checkpoint-page form { margin: 0; }

    .st { --tone: var(--status-todo); }
    .st-fail { --tone: var(--danger-fill); }
    .st-warn { --tone: var(--warn-fill); }
    .st-pass { --tone: var(--ok-fill); }

    .st-chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        flex: none;
        min-width: 64px;
        padding: 3px 10px 3px 9px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--tone) 16%, transparent);
        color: color-mix(in srgb, var(--tone) 52%, var(--text));
        font-size: 0.7rem;
        font-weight: 650;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .st-chip::before { content: ''; width: 7px; height: 7px; border-radius: 999px; background: var(--tone); }

    .verdict {
        --tone: var(--warn-fill);
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 16px 18px;
        border: 1px solid color-mix(in srgb, var(--tone) 40%, var(--border));
        border-radius: var(--radius);
        background: color-mix(in srgb, var(--tone) 9%, var(--surface));
    }

    .verdict.is-pass { --tone: var(--ok-fill); }
    .verdict.is-fail { --tone: var(--danger-fill); }
    .verdict.is-none { --tone: var(--accent); }
    .verdict .icon { flex: none; width: 22px; height: 22px; margin-top: 1px; color: color-mix(in srgb, var(--tone) 70%, var(--text)); }
    .verdict strong { display: block; font-size: 1.02rem; font-weight: 650; }
    .verdict p { margin: 3px 0 0; color: var(--text-2); font-size: 0.9rem; line-height: 1.5; max-width: 90ch; }

    .metric .st-chip { margin-bottom: 8px; }
    .metric-value { font-variant-numeric: tabular-nums; }

    .areas { display: grid; gap: 14px; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 760px) { .areas { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); } }
    .area { padding: 14px 16px; display: flex; flex-direction: column; gap: 8px; }
    .area h3 { margin: 0; font-size: 0.95rem; }
    .area-bar { display: flex; height: 8px; border-radius: 999px; overflow: hidden; background: var(--surface-3); }
    .area-bar span { display: block; height: 100%; background: var(--tone); }
    .area p { margin: 0; color: var(--muted); font-size: 0.8rem; }

    .tools { display: flex; align-items: end; justify-content: space-between; gap: 10px 16px; flex-wrap: wrap; }
    .tools .field { flex: 1 1 240px; max-width: 380px; margin: 0; }
    .toggles { display: flex; flex-wrap: wrap; gap: 6px; }

    .toggle {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 34px;
        padding: 0 12px;
        border: 1px solid var(--border-strong);
        border-radius: 999px;
        background: var(--surface);
        color: var(--text-2);
        font: inherit;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
    }

    .toggle b { color: var(--muted); font-weight: 600; font-variant-numeric: tabular-nums; }
    .toggle:hover { border-color: var(--accent); color: var(--accent); }
    .toggle[aria-pressed="true"] { border-color: color-mix(in srgb, var(--accent) 55%, var(--border)); background: var(--accent-soft); color: var(--accent-strong); }

    .checks { padding: 0; overflow: hidden; }
    .check { border-top: 1px solid var(--border); }
    .check:first-child { border-top: 0; }
    .check[hidden] { display: none; }

    .check > summary {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        align-items: center;
        gap: 8px 14px;
        padding: 13px 18px;
        cursor: pointer;
        list-style: none;
    }

    .check > summary::-webkit-details-marker { display: none; }
    .check > summary:hover, .check[open] > summary { background: var(--surface-2); }
    .check-title strong { display: block; font-size: 0.94rem; font-weight: 600; }
    .check-title small { display: block; margin-top: 2px; color: var(--muted); font-size: 0.8rem; line-height: 1.4; overflow-wrap: anywhere; }
    .check-area { color: var(--muted); font-size: 0.76rem; white-space: nowrap; }

    @media (max-width: 639px) {
        .check > summary { grid-template-columns: minmax(0, 1fr) auto; }
        .check > summary .st-chip { justify-self: start; }
        .check > summary .check-title { grid-column: 1 / -1; grid-row: 2; }
    }

    .check-body { padding: 4px 18px 16px; background: var(--surface-2); }
    .check-body ul { margin: 0; padding: 0; list-style: none; font-size: 0.84rem; }
    .check-body li { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; padding: 7px 0; border-top: 1px solid var(--border); }
    .check-body li:first-child { border-top: 0; }
    .check-body li span { overflow-wrap: anywhere; font-family: var(--mono); font-size: 0.8rem; }
    .check-body li code { flex: none; }
    .check-body p { margin: 10px 0 0; color: var(--muted); font-size: 0.8rem; }

    .history { display: flex; align-items: flex-end; gap: 6px; height: 60px; }
    .history-bar { display: flex; flex-direction: column-reverse; width: 18px; height: 100%; border-radius: 4px 4px 2px 2px; overflow: hidden; background: var(--surface-3); }
    .history-bar span { display: block; width: 100%; background: var(--tone); }
    .history-legend { display: flex; flex-wrap: wrap; gap: 4px 14px; margin-top: 8px; color: var(--muted); font-size: 0.76rem; }
    .history-legend i { display: inline-block; width: 9px; height: 9px; margin-right: 5px; border-radius: 2px; background: var(--tone); vertical-align: -1px; }

    .what-list { margin: 0; padding: 0; list-style: none; display: grid; gap: 6px 18px; grid-template-columns: minmax(0, 1fr); font-size: 0.86rem; }
    @media (min-width: 760px) { .what-list { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    .what-list h4 { margin: 0 0 4px; font-size: 0.8rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.06em; }
    .what-list li { color: var(--text-2); line-height: 1.5; }
    .none { padding: 26px 16px; text-align: center; color: var(--muted); }
</style>
@endpush

@section('content')
    @php
        $when = static fn (?string $iso): string => $iso ? \Illuminate\Support\Carbon::parse($iso)->format('M j, Y · H:i') : '—';
        $statusWord = ['fail' => 'Failed', 'warn' => 'Warning', 'pass' => 'Passed'];
        $history = $scan ? array_slice($scan['history'], -16) : [];
        $catalogue = [
            'Dependencies' => ['Composer and npm CVE audits', 'Package freshness (supply chain)', 'Suspicious vendor autoload', 'End-of-life PHP and Laravel'],
            'Configuration' => ['APP_DEBUG, APP_KEY, secure cookies', '.gitignore and file permissions', 'CORS, sessions, TLS verification', 'Sensitive data exposure'],
            'Code' => ['Hardcoded secrets', 'SQL injection, XSS, CSRF, SSRF', 'Command injection, path traversal, open redirects', 'Weak crypto, insecure RNG, debug calls'],
        ];
    @endphp

    <div class="checkpoint-page">
        <header class="page-head">
            <div>
                <h2>Security</h2>
                <p class="sub">Checkpoint scans the code and the configuration of this project on this machine: known CVEs in the dependencies, hardcoded secrets, injection patterns, weak settings, versions past their end of life. Larapilot runs it and keeps the result.</p>
                @if ($scan)
                    <div class="chips" style="margin-top: 12px">
                        <span class="chip current">Checkpoint {{ $scan['version'] ?? $version ?? '' }}</span>
                        <span class="chip">Scanned {{ $when($scan['scanned_at']) }}</span>
                        @if ($scan['seconds'] !== null)
                            <span class="chip">{{ $scan['seconds'] }} s</span>
                        @endif
                        <span @class(['chip', 'live' => $enforced])>{{ $enforced ? 'Enforced in review and ship' : 'Not enforced (security_scan = NO)' }}</span>
                    </div>
                @endif
            </div>
            @if ($installed)
                <div class="page-actions">
                    <form method="post" action="{{ route('larapilot.dashboard.security.checkpoint.scan') }}">
                        @csrf
                        <button type="submit" class="btn" title="Run php artisan checkpoint:scan now; it can take a minute: the dependency audits ask Packagist and npm">@include('larapilot::dashboard.partials.icon', ['name' => 'refresh']){{ $scan ? 'Scan again' : 'Run the scan' }}</button>
                    </form>
                    @if ($scan)
                        <a class="btn ghost" href="{{ route('larapilot.dashboard.security.checkpoint.report') }}" title="Every check and finding, with its suppression hash">@include('larapilot::dashboard.partials.icon', ['name' => 'download'])Download report (.md)</a>
                    @endif
                </div>
            @endif
        </header>

        @include('larapilot::dashboard.partials.security-tabs', ['current' => 'checkpoint'])

        @if (! $installed && ! $scan)
            <div class="verdict is-none" role="status">
                @include('larapilot::dashboard.partials.icon', ['name' => 'info'])
                <div>
                    <strong>Checkpoint is not installed in this project</strong>
                    <p><a href="https://github.com/andreapollastri/checkpoint" target="_blank" rel="noopener noreferrer">Checkpoint</a> is a free static scanner for Laravel, one Artisan command, 26 checks. Install it as a dev dependency: <code>composer require --dev andreapollastri/checkpoint</code>, then come back and run the scan. To make <code>/larapilot-review</code> and <code>/larapilot-ship</code> stop on what it fails: <code>php artisan larapilot:settings-set --security-scan=YES</code>.</p>
                </div>
            </div>
            <section class="card panel">
                <h3 style="margin: 0 0 12px; font-size: 1rem">What it checks</h3>
                <div class="what-list">
                    @foreach ($catalogue as $area => $items)
                        <div>
                            <h4>{{ $area }}</h4>
                            <ul style="margin: 0; padding: 0; list-style: none">
                                @foreach ($items as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </section>
        @elseif (! $scan)
            <div class="verdict is-none" role="status">
                @include('larapilot::dashboard.partials.icon', ['name' => 'info'])
                <div>
                    <strong>No scan kept yet</strong>
                    <p>Checkpoint {{ $version }} is installed. <em>Run the scan</em> here, or from the terminal: <code>php artisan larapilot:checkpoint-scan</code>. A scan run by <code>/larapilot-review</code> or <code>/larapilot-ship</code> lands here too.</p>
                </div>
            </div>
        @else
            @if (! $installed)
                <div class="flash flash--warn" role="status"><strong>Checkpoint is no longer installed: this is the last scan that was kept.</strong></div>
            @endif

            <div @class(['verdict', 'is-'.strtolower($scan['verdict'])]) role="status">
                @include('larapilot::dashboard.partials.icon', ['name' => $scan['verdict'] === 'PASS' ? 'check' : 'info'])
                <div>
                    <strong>{{ match ($scan['verdict']) { 'PASS' => 'Every check passed', 'WARN' => 'Nothing failed, and there is something to look at', default => 'A check failed' } }}</strong>
                    <p>
                        {{ $scan['counts']['pass'] }} passed, {{ $scan['counts']['warn'] }} {{ $scan['counts']['warn'] === 1 ? 'warning' : 'warnings' }}, {{ $scan['counts']['fail'] }} failed out of {{ $scan['total'] }} checks.
                        @if ($enforced)
                            A failed check is a blocker of <code>/larapilot-review</code> and of the ship gate until it is fixed or waived with a decision.
                        @else
                            Review and ship do not stop on it: turn that on with <code>php artisan larapilot:settings-set --security-scan=YES</code>.
                        @endif
                        @if ($scan['partial'])
                            This was a partial scan{{ $scan['only'] !== [] ? ' (only '.implode(', ', $scan['only']).')' : '' }}{{ $scan['skip'] !== [] ? ' (skipped '.implode(', ', $scan['skip']).')' : '' }}.
                        @endif
                    </p>
                </div>
            </div>

            <section class="metrics" aria-label="Checks by result">
                @foreach (['fail', 'warn', 'pass'] as $status)
                    <div class="card metric st st-{{ $status }}">
                        <span class="st-chip">{{ $statusWord[$status] }}</span>
                        <div class="metric-value">{{ $scan['counts'][$status] }}</div>
                        <div class="metric-note">{{ $scan['counts'][$status] === 1 ? 'check' : 'checks' }}</div>
                    </div>
                @endforeach
                <div class="card metric">
                    <div class="metric-label">Findings</div>
                    <div class="metric-value">{{ $scan['findings'] }}</div>
                    <div class="metric-note">{{ $scan['suppressed'] > 0 ? $scan['suppressed'].' suppressed in config/checkpoint.php' : 'lines and settings to look at' }}</div>
                </div>
            </section>

            <section class="areas" aria-label="Results by area">
                @foreach ($scan['areas'] as $area)
                    @php $areaTotal = max(1, $area['fail'] + $area['warn'] + $area['pass']); @endphp
                    <article class="card area">
                        <h3>{{ $area['area'] }}</h3>
                        <div class="area-bar" role="img" aria-label="{{ $area['area'] }}: {{ $area['fail'] }} failed, {{ $area['warn'] }} warnings, {{ $area['pass'] }} passed">
                            @foreach (['fail', 'warn', 'pass'] as $status)
                                @if ($area[$status] > 0)
                                    <span class="st-{{ $status }}" style="width: {{ round($area[$status] / $areaTotal * 100, 2) }}%"></span>
                                @endif
                            @endforeach
                        </div>
                        <p>{{ $area['fail'] }} failed · {{ $area['warn'] }} warnings · {{ $area['pass'] }} passed</p>
                    </article>
                @endforeach
            </section>

            <div class="tools">
                <label class="field">
                    Find a check
                    <input type="search" id="check-filter" placeholder="A check, a file, a word…" autocomplete="off">
                </label>
                <div class="toggles" role="group" aria-label="Show by result">
                    <button type="button" class="toggle" data-status="" aria-pressed="true">All <b>{{ $scan['total'] }}</b></button>
                    <button type="button" class="toggle" data-status="fail" aria-pressed="false">Failed <b>{{ $scan['counts']['fail'] }}</b></button>
                    <button type="button" class="toggle" data-status="warn" aria-pressed="false">Warnings <b>{{ $scan['counts']['warn'] }}</b></button>
                    <button type="button" class="toggle" data-status="pass" aria-pressed="false">Passed <b>{{ $scan['counts']['pass'] }}</b></button>
                </div>
            </div>

            <section class="card checks" aria-label="Checks, failed first">
                @foreach ($scan['checks'] as $check)
                    <details class="check st st-{{ $check['status'] }}" data-status="{{ $check['status'] }}" data-find="{{ strtolower($check['check'].' '.$check['area'].' '.$check['message'].' '.implode(' ', array_column($check['details'], 'text'))) }}" @if ($check['status'] === 'fail' && $loop->first) open @endif>
                        <summary>
                            <span class="st-chip">{{ $check['status'] }}</span>
                            <span class="check-title">
                                <strong>{{ $check['check'] }}</strong>
                                <small>{{ $check['message'] }}</small>
                            </span>
                            <span class="check-area">{{ $check['area'] }}{{ $check['details'] !== [] ? ' · '.count($check['details']) : '' }}</span>
                        </summary>
                        @if ($check['details'] !== [])
                            <div class="check-body">
                                <ul>
                                    @foreach ($check['details'] as $detail)
                                        <li><span>{{ $detail['text'] }}</span>@if ($detail['hash'])<code title="Add this hash to suppressed in config/checkpoint.php to silence it">{{ $detail['hash'] }}</code>@endif</li>
                                    @endforeach
                                </ul>
                                @if ($check['status'] !== 'pass')
                                    <p>A false positive is silenced by its hash, under <code>suppressed</code> in <code>config/checkpoint.php</code>. A real one goes to the backlog through <code>/larapilot-triage</code>.</p>
                                @endif
                            </div>
                        @endif
                    </details>
                @endforeach
                <p class="none" id="check-none" hidden>No check matches.</p>
            </section>

            @if (count($history) > 1)
                <section class="card panel">
                    <h3 style="margin: 0 0 10px; font-size: 1rem">Scans over time</h3>
                    <div class="history" role="img" aria-label="Failed and warning checks per scan, oldest first">
                        @foreach ($history as $row)
                            @php $rowTotal = max(1, (int) ($row['fail'] ?? 0) + (int) ($row['warn'] ?? 0) + (int) ($row['pass'] ?? 0)); @endphp
                            <div class="history-bar" title="{{ $when($row['scanned_at'] ?? null) }} — {{ (int) ($row['fail'] ?? 0) }} failed, {{ (int) ($row['warn'] ?? 0) }} warnings, {{ (int) ($row['pass'] ?? 0) }} passed{{ ! empty($row['partial']) ? ' (partial)' : '' }}">
                                @foreach (['pass', 'warn', 'fail'] as $status)
                                    @if ((int) ($row[$status] ?? 0) > 0)
                                        <span class="st-{{ $status }}" style="height: {{ round((int) $row[$status] / $rowTotal * 100, 2) }}%"></span>
                                    @endif
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    <div class="history-legend">
                        <span class="st-fail"><i></i>Failed</span>
                        <span class="st-warn"><i></i>Warnings</span>
                        <span class="st-pass"><i></i>Passed</span>
                    </div>
                </section>
            @endif

            <p class="footer-note" style="margin: 0; text-align: left">
                The scan is <code>php artisan checkpoint:scan --json</code>, run by <code>php artisan larapilot:checkpoint-scan</code>; the result stays in <code>.larapilot/cache/checkpoint/</code> on this machine, out of git — the details can quote code. Tune it in <code>config/checkpoint.php</code> (<code>php artisan vendor:publish --tag=checkpoint-config</code>); wire it into CI with <code>php artisan checkpoint:github</code> or <code>checkpoint:gitlab</code>.
            </p>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    (() => {
        const checks = [...document.querySelectorAll('.check')];
        const filter = document.getElementById('check-filter');
        const toggles = [...document.querySelectorAll('.toggle[data-status]')];
        const none = document.getElementById('check-none');

        if (checks.length === 0) {
            return;
        }

        let status = '';

        const apply = () => {
            const words = (filter ? filter.value : '').trim().toLowerCase().split(/\s+/).filter(Boolean);
            let shown = 0;

            checks.forEach((check) => {
                const ok = (status === '' || check.dataset.status === status) && words.every((word) => (check.dataset.find || '').includes(word));
                check.hidden = !ok;
                shown += ok ? 1 : 0;
            });

            if (none) {
                none.hidden = shown > 0;
            }
        };

        toggles.forEach((toggle) => toggle.addEventListener('click', () => {
            status = toggle.dataset.status || '';
            toggles.forEach((other) => other.setAttribute('aria-pressed', other === toggle ? 'true' : 'false'));
            apply();
        }));

        filter?.addEventListener('input', apply);
    })();
</script>
@endpush
