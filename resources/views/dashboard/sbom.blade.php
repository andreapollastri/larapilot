@extends('larapilot::dashboard.layout')

@section('title', 'SBOM')

@push('styles')
<style>
    .sbom-page { display: flex; flex-direction: column; gap: 20px; }
    .sbom-page .page-head, .sbom-page .metrics { margin-bottom: 0; }
    .sbom-page .page-head > :first-child { flex-basis: 520px; }
    .sbom-page form { margin: 0; }

    .sev { --tone: var(--status-todo); }
    .sev-critical { --tone: var(--danger-fill); }
    .sev-high { --tone: #ec835a; }
    .sev-medium { --tone: var(--warn-fill); }
    .sev-low { --tone: var(--status-todo); }
    .sev-unknown { --tone: var(--border-strong); }

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

    .metric-value { font-variant-numeric: tabular-nums; }

    .inventories { display: grid; gap: 14px; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 760px) { .inventories { grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); } }

    .inventory { padding: 16px 18px; display: flex; flex-direction: column; gap: 6px; min-width: 0; }
    .inventory h3 { margin: 0; font-size: 0.98rem; }
    .inventory .count { font-size: 1.5rem; font-weight: 650; font-variant-numeric: tabular-nums; }
    .inventory p { margin: 0; color: var(--muted); font-size: 0.82rem; line-height: 1.45; overflow-wrap: anywhere; }
    .inventory .problem { color: var(--warn); }

    .section-head { display: flex; align-items: baseline; justify-content: space-between; gap: 8px 16px; flex-wrap: wrap; margin-bottom: 12px; }
    .section-head h3 { margin: 0; font-size: 1.02rem; }
    .section-head .hint { margin: 0; }

    .vuln-list { padding: 0; overflow: hidden; }
    .vuln { border-top: 1px solid var(--border); }
    .vuln:first-child { border-top: 0; }

    .vuln > summary {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        align-items: center;
        gap: 8px 14px;
        padding: 14px 18px;
        cursor: pointer;
        list-style: none;
    }

    .vuln > summary::-webkit-details-marker { display: none; }
    .vuln > summary:hover, .vuln[open] > summary { background: var(--surface-2); }
    .vuln-title strong { display: block; font-size: 0.95rem; font-weight: 600; overflow-wrap: anywhere; }
    .vuln-title small { display: block; margin-top: 2px; color: var(--muted); font-size: 0.78rem; }
    .vuln-fixed { font-size: 0.8rem; color: var(--text-2); white-space: nowrap; }

    @media (max-width: 639px) {
        .vuln > summary { grid-template-columns: minmax(0, 1fr) auto; }
        .vuln > summary .sev-chip { justify-self: start; }
        .vuln > summary .vuln-title { grid-column: 1 / -1; grid-row: 2; }
    }

    .vuln-body { padding: 4px 18px 18px; background: var(--surface-2); display: flex; flex-direction: column; gap: 12px; }
    .vuln-body h4 { margin: 0 0 4px; color: var(--muted); font-size: 0.68rem; font-weight: 650; letter-spacing: 0.08em; text-transform: uppercase; }
    .vuln-body pre { margin: 0; padding: 10px 12px; border: 1px solid var(--border); border-radius: var(--radius-xs); background: var(--surface); font-size: 0.82rem; white-space: pre-wrap; overflow-wrap: anywhere; }

    .advisories { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
    .advisories th, .advisories td { padding: 7px 8px; border-top: 1px solid var(--border); text-align: left; vertical-align: top; }
    .advisories thead th { border-top: 0; color: var(--muted); font-size: 0.68rem; font-weight: 650; letter-spacing: 0.06em; text-transform: uppercase; }
    .advisories td:first-child { white-space: nowrap; }

    .state { display: inline-flex; padding: 2px 9px; border-radius: 999px; border: 1px solid var(--border); font-size: 0.72rem; font-weight: 600; white-space: nowrap; }
    .state.is-open { border-color: color-mix(in srgb, var(--warn-fill) 55%, var(--border)); color: var(--warn); }
    .state.is-in_backlog { border-color: color-mix(in srgb, var(--accent) 45%, var(--border)); color: var(--accent-strong); }
    .state.is-waived { color: var(--muted); }

    .history { display: flex; align-items: flex-end; gap: 6px; height: 64px; padding: 4px 0; }
    .history-bar { display: flex; flex-direction: column-reverse; width: 18px; min-height: 3px; border-radius: 4px 4px 2px 2px; overflow: hidden; background: var(--surface-3); }
    .history-bar span { display: block; width: 100%; }
    .history-legend { display: flex; flex-wrap: wrap; gap: 4px 14px; margin-top: 8px; color: var(--muted); font-size: 0.76rem; }
    .history-legend i { display: inline-block; width: 9px; height: 9px; margin-right: 5px; border-radius: 2px; background: var(--tone); vertical-align: -1px; }

    .license-bar { display: flex; height: 12px; border-radius: 999px; overflow: hidden; background: var(--surface-3); }
    .license-bar span { display: block; height: 100%; }
    .lc-permissive { --tone: var(--ok-fill); }
    .lc-weak-copyleft { --tone: var(--warn-fill); }
    .lc-strong-copyleft { --tone: var(--danger-fill); }
    .lc-unknown { --tone: var(--border-strong); }
    .license-bar span, .license-key i { background: var(--tone); }
    .license-key { display: flex; flex-wrap: wrap; gap: 6px 16px; margin: 10px 0 0; padding: 0; list-style: none; font-size: 0.8rem; color: var(--text-2); }
    .license-key i { display: inline-block; width: 10px; height: 10px; margin-right: 6px; border-radius: 3px; vertical-align: -1px; }

    .license-grid { display: grid; gap: 18px; grid-template-columns: minmax(0, 1fr); margin-top: 16px; }
    @media (min-width: 900px) { .license-grid { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); } }
    .license-grid h4 { margin: 0 0 8px; font-size: 0.86rem; }
    .compact-list { margin: 0; padding: 0; list-style: none; font-size: 0.85rem; }
    .compact-list li { display: flex; justify-content: space-between; gap: 10px; padding: 6px 0; border-top: 1px solid var(--border); }
    .compact-list li:first-child { border-top: 0; }
    .compact-list small { color: var(--muted); }

    .tools { display: flex; align-items: end; justify-content: space-between; gap: 10px 16px; flex-wrap: wrap; margin-bottom: 12px; }
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

    .components { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
    .components th, .components td { padding: 8px 10px; border-top: 1px solid var(--border); text-align: left; vertical-align: top; }
    .components thead th { position: sticky; top: 0; z-index: 1; border-top: 0; background: var(--surface); color: var(--muted); font-size: 0.68rem; font-weight: 650; letter-spacing: 0.06em; text-transform: uppercase; white-space: nowrap; }
    .components td.name { overflow-wrap: anywhere; min-width: 180px; }
    .components td.name small { display: block; color: var(--muted); font-size: 0.74rem; }
    .components tr[hidden] { display: none; }
    .components-scroll { max-height: 70vh; overflow: auto; }
    .muted { color: var(--muted); }
    .none { padding: 26px 16px; text-align: center; color: var(--muted); }
</style>
@endpush

@section('content')
    @php
        $totals = $inventory['totals'];
        $licenses = $inventory['licenses'];
        $when = static fn (?string $iso): string => $iso ? \Illuminate\Support\Carbon::parse($iso)->format('M j, Y · H:i') : '—';
        $inventoryLabels = collect($inventory['inventories'])->mapWithKeys(fn ($item) => [$item['id'] => $item['label']])->all();
        $vulnerablePackages = $audit ? count(array_filter($audit['packages'], static fn ($package) => $package['open'] > 0)) : null;
        $copyleft = ($licenses['classes']['weak-copyleft'] ?? 0) + ($licenses['classes']['strong-copyleft'] ?? 0);
        $severityWord = ['critical' => 'Critical', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low', 'unknown' => 'Unrated'];
        $stateWord = ['open' => 'Open', 'in_backlog' => 'In the backlog', 'waived' => 'Waived'];
        $history = $audit ? array_slice($audit['history'], -16) : [];
        $historyMax = max(1, ...array_map(static fn ($row) => (int) ($row['critical'] ?? 0) + (int) ($row['high'] ?? 0) + (int) ($row['medium'] ?? 0) + (int) ($row['low'] ?? 0) + (int) ($row['unknown'] ?? 0), $history ?: [[]]));
        $classWord = ['permissive' => 'Permissive', 'weak-copyleft' => 'Weak copyleft', 'strong-copyleft' => 'Strong copyleft', 'unknown' => 'Not declared'];
        $classTotal = max(1, array_sum($licenses['classes']));
    @endphp

    <div class="sbom-page">
        <header class="page-head">
            <div>
                <h2>SBOM</h2>
                <p class="sub">Every package the project ships — Composer for Laravel, the JavaScript of this repository, and the external frontend the companion links — read from the lockfiles, with its license and its known vulnerabilities.</p>
                <div class="chips" style="margin-top: 12px">
                    @foreach ($inventory['inventories'] as $item)
                        <span class="chip">{{ $item['label'] }} · {{ number_format($item['count']) }}</span>
                    @endforeach
                    @if ($audit)
                        <span class="chip">Checked {{ $when($audit['checked_at']) }}</span>
                    @endif
                </div>
            </div>
            <div class="page-actions">
                <form method="post" action="{{ route('larapilot.dashboard.sbom.audit') }}">
                    @csrf
                    <button type="submit" class="btn" title="Send the name and version of each package to OSV.dev and list the advisories that affect them">@include('larapilot::dashboard.partials.icon', ['name' => 'refresh'])Check vulnerabilities</button>
                </form>
                <a class="btn ghost" href="{{ route('larapilot.dashboard.sbom.download') }}" title="The bill of materials with licenses and vulnerabilities, as Markdown">@include('larapilot::dashboard.partials.icon', ['name' => 'download'])SBOM (.md)</a>
                <a class="btn ghost" href="{{ route('larapilot.dashboard.sbom.cyclonedx') }}" title="CycloneDX 1.5 JSON, for Dependency-Track and other SBOM tools">@include('larapilot::dashboard.partials.icon', ['name' => 'download'])CycloneDX (.json)</a>
                @if ($audit)
                    <a class="btn ghost" href="{{ route('larapilot.dashboard.sbom.report') }}" title="Each advisory with its fix, for the team">@include('larapilot::dashboard.partials.icon', ['name' => 'download'])Vulnerabilities (.md)</a>
                @endif
            </div>
        </header>

        @if ($audit === null)
            <div class="verdict is-none" role="status">
                @include('larapilot::dashboard.partials.icon', ['name' => 'info'])
                <div>
                    <strong>Not checked for vulnerabilities yet</strong>
                    <p><em>Check vulnerabilities</em> sends the name and the version of each package — nothing else — to <a href="https://osv.dev" target="_blank" rel="noopener noreferrer">OSV.dev</a>, the open database behind the GitHub advisories, FriendsOfPHP, and npm. From the terminal: <code>php artisan larapilot:vendor-audit</code>; with the agent: <code>/larapilot-vendor-check</code>.</p>
                </div>
            </div>
        @else
            <div @class(['verdict', 'is-'.strtolower($gate['verdict'])]) role="status">
                @include('larapilot::dashboard.partials.icon', ['name' => $gate['verdict'] === 'PASS' ? 'check' : 'info'])
                <div>
                    <strong>{{ match ($gate['verdict']) { 'PASS' => 'No known vulnerability is open', 'WARN' => 'Open vulnerabilities below the gate', default => 'Vulnerabilities stop a release' } }}</strong>
                    <p>{{ $gate['summary'] }} {{ number_format($audit['components']) }} packages checked against {{ $audit['source'] }} on {{ $when($audit['checked_at']) }}. The ship gate stops on an open advisory that is <strong style="display: inline; font-size: inherit">{{ $gate['fail_on'] }}</strong> or above and was not waived; one in the backlog counts until the package is updated.
                        @if ($audit['stale'])
                            <strong style="display: inline; font-size: inherit">The lockfiles changed since: check again.</strong>
                        @endif
                    </p>
                </div>
            </div>
        @endif

        <section class="metrics" aria-label="Totals">
            <div class="card metric">
                <div class="metric-label">Components</div>
                <div class="metric-value">{{ number_format($totals['components']) }}</div>
                <div class="metric-note">{{ collect($totals['by_ecosystem'])->map(fn ($count, $ecosystem) => number_format($count).' '.($ecosystem === 'composer' ? 'Composer' : 'npm'))->implode(' · ') ?: 'no lockfile' }}</div>
            </div>
            <div class="card metric">
                <div class="metric-label">Direct</div>
                <div class="metric-value">{{ number_format($totals['direct']) }}</div>
                <div class="metric-note">asked for by the project; the rest come with them</div>
            </div>
            <div class="card metric">
                <div class="metric-label">Production · development</div>
                <div class="metric-value">{{ number_format($totals['prod']) }} <span class="muted" style="font-size: 0.9rem">· {{ number_format($totals['dev']) }}</span></div>
                <div class="metric-note">{{ $totals['components'] - $totals['prod'] - $totals['dev'] > 0 ? number_format($totals['components'] - $totals['prod'] - $totals['dev']).' the lockfile does not say' : 'what ships, and what only builds or tests' }}</div>
            </div>
            <div class="card metric">
                <div class="metric-label">Vulnerable packages</div>
                <div class="metric-value">{{ $vulnerablePackages === null ? '—' : number_format($vulnerablePackages) }}</div>
                <div class="metric-note">{{ $audit ? number_format($audit['open']).' open advisories' : 'not checked yet' }}</div>
            </div>
            <div class="card metric">
                <div class="metric-label">Copyleft · abandoned</div>
                <div class="metric-value">{{ number_format($copyleft) }} <span class="muted" style="font-size: 0.9rem">· {{ number_format($totals['abandoned']) }}</span></div>
                <div class="metric-note">licenses that oblige to share code; packages nobody maintains</div>
            </div>
        </section>

        <section class="inventories" aria-label="Inventories">
            @foreach ($inventory['inventories'] as $item)
                <article class="card inventory">
                    <h3>{{ $item['label'] }}</h3>
                    <div class="count">{{ number_format($item['count']) }}</div>
                    <p>{{ $item['where'] }}{{ $item['file'] ? ' · '.$item['file'] : '' }}{{ $item['manager'] && $item['manager'] !== 'composer' ? ' · '.$item['manager'] : '' }}</p>
                    @if ($item['error'])
                        <p class="problem">{{ $item['error'] }}</p>
                    @endif
                    @if ($item['note'])
                        <p>{{ $item['note'] }}</p>
                    @endif
                </article>
            @endforeach
            @if (count($inventory['inventories']) < 3)
                <article class="card inventory">
                    <h3>Frontend companion</h3>
                    <p>No external frontend is linked. When the frontend lives in its own repository, link it with <code>/larapilot-frontend-companion</code> and its lockfile joins the SBOM.</p>
                </article>
            @endif
        </section>

        @if ($audit && $audit['packages'] !== [])
            <section>
                <div class="section-head">
                    <h3>Vulnerabilities by package</h3>
                    <p class="hint">Fix them with <code>/larapilot-vendor-check</code>: update now, hand to the backlog, or waive with a reason.</p>
                </div>
                <section class="metrics" aria-label="Open advisories by severity" style="margin-bottom: 14px">
                    @foreach (\Larapilot\Services\VendorAuditService::SEVERITIES as $severity)
                        @if ($severity !== 'unknown' || $audit['counts']['unknown'] > 0)
                            <div class="card metric sev sev-{{ $severity }}">
                                <span class="sev-chip">{{ $severityWord[$severity] }}</span>
                                <div class="metric-value">{{ number_format($audit['counts'][$severity]) }}</div>
                                <div class="metric-note">open {{ $audit['counts'][$severity] === 1 ? 'advisory' : 'advisories' }}</div>
                            </div>
                        @endif
                    @endforeach
                </section>
                <div class="card vuln-list">
                    @foreach ($audit['packages'] as $package)
                        @php $packageFindings = array_values(array_filter($audit['findings'], fn ($finding) => $finding['ecosystem'] === $package['ecosystem'] && $finding['package'] === $package['package'] && $finding['version'] === $package['version'])); @endphp
                        <details class="vuln sev sev-{{ $package['severity'] }}">
                            <summary>
                                <span class="sev-chip">{{ $severityWord[$package['severity']] ?? $package['severity'] }}</span>
                                <span class="vuln-title">
                                    <strong>{{ $package['package'] }} {{ $package['version'] }}</strong>
                                    <small>{{ $package['ecosystem'] === 'composer' ? 'Composer' : 'npm' }} · {{ implode(', ', array_map(fn ($id) => $inventoryLabels[$id] ?? $id, $package['inventories'])) }}{{ $package['direct'] ? ' · direct' : ' · transitive' }}{{ $package['scope'] === 'dev' ? ' · dev' : '' }} · {{ count($package['ids']) }} {{ count($package['ids']) === 1 ? 'advisory' : 'advisories' }}{{ $package['open'] !== count($package['ids']) ? ' ('.$package['open'].' open)' : '' }}</small>
                                </span>
                                <span class="vuln-fixed">{{ $package['fixed'] ? 'Fixed in '.$package['fixed'] : 'No fix published' }}</span>
                            </summary>
                            <div class="vuln-body">
                                <div>
                                    <h4>Fix</h4>
                                    <pre>{{ $package['fix'] }}</pre>
                                </div>
                                <div class="table-wrap">
                                    <table class="advisories">
                                        <thead><tr><th>Advisory</th><th>Severity</th><th>Summary</th><th>Decision</th></tr></thead>
                                        <tbody>
                                            @foreach ($packageFindings as $finding)
                                                <tr>
                                                    <td><a href="{{ $finding['url'] }}" target="_blank" rel="noopener noreferrer">{{ $finding['id'] }}</a>@foreach ($finding['aliases'] as $alias)<br><small class="muted">{{ $alias }}</small>@endforeach</td>
                                                    <td>{{ $severityWord[$finding['severity']] ?? $finding['severity'] }}{{ $finding['score'] ? ' · '.$finding['score'] : '' }}</td>
                                                    <td>{{ $finding['summary'] }}</td>
                                                    <td><span class="state is-{{ $finding['state'] }}">{{ $finding['state'] === 'in_backlog' ? $finding['spec'] : $stateWord[$finding['state']] }}</span>@if ($finding['state'] === 'waived' && $finding['reason'])<br><small class="muted">{{ $finding['reason'] }}</small>@endif</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </details>
                    @endforeach
                </div>
            </section>
        @endif

        @if (count($history) > 1)
            <section class="card panel">
                <div class="section-head">
                    <h3>Checks over time</h3>
                    <p class="hint">Open advisories at each of the last {{ count($history) }} checks. Kept in <code>{{ $audit['ledger'] }}</code>.</p>
                </div>
                <div class="history" role="img" aria-label="Open advisories per check, oldest first: {{ implode(', ', array_map(static fn ($row) => (int) ($row['critical'] ?? 0) + (int) ($row['high'] ?? 0) + (int) ($row['medium'] ?? 0) + (int) ($row['low'] ?? 0), $history)) }}">
                    @foreach ($history as $row)
                        @php $sum = (int) ($row['critical'] ?? 0) + (int) ($row['high'] ?? 0) + (int) ($row['medium'] ?? 0) + (int) ($row['low'] ?? 0) + (int) ($row['unknown'] ?? 0); @endphp
                        <div class="history-bar" style="height: {{ max(4, round($sum / $historyMax * 100)) }}%" title="{{ $when($row['checked_at'] ?? null) }} — {{ $sum }} open ({{ (int) ($row['critical'] ?? 0) }} critical, {{ (int) ($row['high'] ?? 0) }} high, {{ (int) ($row['medium'] ?? 0) }} medium, {{ (int) ($row['low'] ?? 0) }} low)">
                            @foreach (['critical', 'high', 'medium', 'low'] as $severity)
                                @if ((int) ($row[$severity] ?? 0) > 0)
                                    <span class="sev sev-{{ $severity }}" style="flex: {{ (int) $row[$severity] }} 0 0; background: var(--tone)"></span>
                                @endif
                            @endforeach
                        </div>
                    @endforeach
                </div>
                <div class="history-legend">
                    @foreach (['critical', 'high', 'medium', 'low'] as $severity)
                        <span class="sev sev-{{ $severity }}"><i></i>{{ $severityWord[$severity] }}</span>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="card panel">
            <div class="section-head">
                <h3>Licenses</h3>
                <p class="hint">A choice of licenses counts as the most permissive one. The license is read from the lockfile, or from <code>node_modules</code> when it is installed.</p>
            </div>
            <div class="license-bar" role="img" aria-label="{{ collect($licenses['classes'])->map(fn ($count, $class) => $classWord[$class].' '.$count)->implode(', ') }}">
                @foreach ($licenses['classes'] as $class => $count)
                    @if ($count > 0)
                        <span class="lc-{{ $class }}" style="width: {{ round($count / $classTotal * 100, 2) }}%" title="{{ $classWord[$class] }}: {{ $count }}"></span>
                    @endif
                @endforeach
            </div>
            <ul class="license-key">
                @foreach ($licenses['classes'] as $class => $count)
                    <li class="lc-{{ $class }}"><i></i>{{ $classWord[$class] }} · {{ number_format($count) }}</li>
                @endforeach
            </ul>
            <div class="license-grid">
                <div>
                    <h4>Most used</h4>
                    <ul class="compact-list">
                        @foreach (array_slice($licenses['licenses'], 0, 10) as $license)
                            <li><span>{{ $license['license'] }}</span><small>{{ number_format($license['count']) }} · {{ $classWord[$license['class']] }}</small></li>
                        @endforeach
                    </ul>
                </div>
                <div>
                    <h4>Copyleft components</h4>
                    @if ($licenses['copyleft'] === [])
                        <p class="hint" style="margin: 0">None.</p>
                    @else
                        <ul class="compact-list">
                            @foreach (array_slice($licenses['copyleft'], 0, 12) as $component)
                                <li><span>{{ $component['name'] }} <small>{{ $component['version'] }}</small></span><small>{{ $component['license'] }}{{ $component['scope'] ? ' · '.$component['scope'] : '' }}</small></li>
                            @endforeach
                        </ul>
                        @if (count($licenses['copyleft']) > 12)
                            <p class="hint" style="margin: 8px 0 0">… and {{ count($licenses['copyleft']) - 12 }} more in the export.</p>
                        @endif
                    @endif
                </div>
            </div>
        </section>

        <section class="card panel">
            <div class="section-head">
                <h3>Components</h3>
                <p class="hint">{{ number_format($totals['components']) }} packages, one row per name and version.</p>
            </div>
            @if ($inventory['components'] === [])
                <div class="empty"><p>No lockfile to read. Run <code>composer install</code> (and the package manager of the frontend) so the versions are fixed.</p></div>
            @else
                <div class="tools">
                    <label class="field">
                        Find a package
                        <input type="search" id="component-filter" placeholder="A name, a license, a version…" autocomplete="off">
                    </label>
                    <div class="toggles" role="group" aria-label="Narrow the list">
                        <button type="button" class="toggle" data-inventory="" aria-pressed="true">All <b>{{ number_format($totals['components']) }}</b></button>
                        @foreach ($inventory['inventories'] as $item)
                            @if ($item['count'] > 0)
                                <button type="button" class="toggle" data-inventory="{{ $item['id'] }}" aria-pressed="false">{{ $item['label'] }} <b>{{ number_format($item['count']) }}</b></button>
                            @endif
                        @endforeach
                        <button type="button" class="toggle" data-flag="direct" aria-pressed="false">Direct only</button>
                        @if ($audit)
                            <button type="button" class="toggle" data-flag="vulnerable" aria-pressed="false">Vulnerable only</button>
                        @endif
                    </div>
                </div>
                <div class="components-scroll table-wrap">
                    <table class="components">
                        <thead><tr><th>Package</th><th>Version</th><th>Inventory</th><th>Scope</th><th>License</th><th>Vulnerabilities</th></tr></thead>
                        <tbody>
                            @foreach ($inventory['components'] as $component)
                                @php
                                    $hit = $vulnerable[$component['ecosystem'].'|'.strtolower($component['name']).'|'.$component['version']] ?? null;
                                    $license = $component['license'] !== [] ? implode(' OR ', $component['license']) : '';
                                @endphp
                                <tr data-inventory="{{ $component['inventory'] }}" data-direct="{{ $component['direct'] ? '1' : '0' }}" data-vulnerable="{{ $hit && $hit['open'] > 0 ? '1' : '0' }}" data-find="{{ strtolower($component['name'].' '.$component['version'].' '.$license.' '.($component['scope'] ?? '')) }}">
                                    <td class="name"><strong>{{ $component['name'] }}</strong>@if ($component['direct'])<small>direct{{ $component['constraint'] ? ' · '.$component['constraint'] : '' }}</small>@endif @if ($component['abandoned'] !== false)<small style="color: var(--warn)">abandoned{{ is_string($component['abandoned']) ? ' → '.$component['abandoned'] : '' }}</small>@endif</td>
                                    <td><code>{{ $component['version'] }}</code></td>
                                    <td>{{ $inventoryLabels[$component['inventory']] ?? $component['inventory'] }}</td>
                                    <td>{{ $component['scope'] ?? '—' }}</td>
                                    <td>{!! $license !== '' ? e($license) : '<span class="muted">—</span>' !!}</td>
                                    <td>
                                        @if ($hit)
                                            <span class="sev sev-{{ $hit['severity'] }}"><span class="sev-chip">{{ count($hit['ids']) }} · {{ $severityWord[$hit['severity']] ?? $hit['severity'] }}</span></span>
                                        @elseif ($audit)
                                            <span class="muted">none known</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="none" id="component-none" hidden>No package matches.</p>
                </div>
            @endif
        </section>

        <p class="footer-note" style="margin: 0; text-align: left">
            From the terminal: <code>php artisan larapilot:sbom</code> (summary), <code>--write=both</code> to save <code>sbom.md</code> and <code>sbom.cdx.json</code> under the security docs; <code>php artisan larapilot:vendor-audit --gate</code> fails a pipeline on an open advisory. Decisions are kept in <code>.larapilot/vendor-audit.yaml</code>: commit it.
        </p>
    </div>
@endsection

@push('scripts')
<script>
    (() => {
        const rows = [...document.querySelectorAll('.components tbody tr')];
        const filter = document.getElementById('component-filter');
        const none = document.getElementById('component-none');

        if (rows.length === 0) {
            return;
        }

        const inventoryToggles = [...document.querySelectorAll('.toggle[data-inventory]')];
        const flagToggles = [...document.querySelectorAll('.toggle[data-flag]')];
        let inventory = '';

        const apply = () => {
            const words = (filter ? filter.value : '').trim().toLowerCase().split(/\s+/).filter(Boolean);
            const flags = flagToggles.filter((toggle) => toggle.getAttribute('aria-pressed') === 'true').map((toggle) => toggle.dataset.flag);
            let shown = 0;

            rows.forEach((row) => {
                const text = row.dataset.find || '';
                const ok = (inventory === '' || row.dataset.inventory === inventory)
                    && flags.every((flag) => row.dataset[flag] === '1')
                    && words.every((word) => text.includes(word));

                row.hidden = !ok;
                shown += ok ? 1 : 0;
            });

            if (none) {
                none.hidden = shown > 0;
            }
        };

        inventoryToggles.forEach((toggle) => toggle.addEventListener('click', () => {
            inventory = toggle.dataset.inventory || '';
            inventoryToggles.forEach((other) => other.setAttribute('aria-pressed', other === toggle ? 'true' : 'false'));
            apply();
        }));

        flagToggles.forEach((toggle) => toggle.addEventListener('click', () => {
            toggle.setAttribute('aria-pressed', toggle.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
            apply();
        }));

        filter?.addEventListener('input', apply);
    })();
</script>
@endpush
