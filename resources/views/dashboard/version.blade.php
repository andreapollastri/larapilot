@extends('larapilot::dashboard.layout')

@section('title', 'v'.$version['version'])

@push('styles')
<style>
    .version-crumb { margin: 0 0 4px; font-size: 0.8rem; }
    .version-head-chips { display: inline-flex; gap: 6px; vertical-align: middle; margin-left: 8px; }
    .version-bar { height: 8px; border-radius: 999px; background: var(--surface-3); overflow: hidden; display: flex; margin-top: 10px; }
    .version-bar span { display: block; height: 100%; }
    .version-bar .is-done { background: var(--status-done); }
    .version-bar .is-wip { background: var(--status-progress); }
    .version-panel { padding: 18px; margin-bottom: 18px; }
    .version-panel h3 { margin: 0 0 4px; }
    .version-panel > .hint { margin: 0 0 12px; }
    .version-table { width: 100%; min-width: 560px; border-collapse: collapse; font-size: 0.84rem; }
    .version-table th, .version-table td { padding: 9px 10px; border-bottom: 1px solid var(--border); text-align: left; vertical-align: top; }
    .version-table th { color: var(--muted); font-size: 0.7rem; font-weight: 650; letter-spacing: 0.08em; text-transform: uppercase; }
    .version-table td.num, .version-table th.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .version-table tbody tr:hover { background: var(--surface-2); }
    .version-table code { font-size: 0.8rem; }
    .version-notes { border-top: 1px solid var(--border); padding-top: 6px; }
    .version-notes h1 { font-size: 1.1rem; }
    .version-notes h2 { font-size: 0.95rem; margin-top: 16px; }
    .version-columns { display: grid; gap: 18px; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); }
    @media (max-width: 900px) { .version-columns { grid-template-columns: minmax(0, 1fr); } }
    .version-list { margin: 0; padding-left: 1.1em; font-size: 0.86rem; }
    .version-list li { margin: 3px 0; }
</style>
@endpush

@section('content')
    @php
        $statusLabel = ['shipped' => 'Shipped', 'in_progress' => 'In progress', 'planned' => 'Planned'];
        $riskLabel = ['late' => 'Late', 'overdue' => 'Overdue', 'on_track' => 'On track'];
        $typeLabel = ['feature' => 'Feature', 'enhancement' => 'Enhancement', 'change' => 'Change', 'fix' => 'Fix'];
        $views = app(\Larapilot\Services\BoardViewService::class);
        $access = app(\Larapilot\Services\Auth\DashboardSession::class);
        $counts = $version['counts'];
        $share = static fn (int $part): int => $counts['total'] > 0 ? (int) round($part / $counts['total'] * 100) : 0;
        $scopeLink = ['version' => $version['version']];
    @endphp

    <header class="page-head">
        <div>
            <p class="version-crumb"><a href="{{ route('larapilot.dashboard.versions') }}">Versions</a> /</p>
            <h2>v{{ $version['version'] }}{{ $version['title'] !== '' ? ' — '.$version['title'] : '' }}
                <span class="version-head-chips">
                    <span @class(['chip', 'current' => $version['current'], 'live' => $version['shipped']])>{{ $statusLabel[$version['status']] ?? $version['status'] }}</span>
                    @if (in_array($version['risk'], ['late', 'overdue'], true))
                        <span class="chip stale">{{ $riskLabel[$version['risk']] }}</span>
                    @endif
                </span>
            </h2>
            <p class="sub">
                @if ($version['shipped'])
                    Shipped {{ $version['shipped_at'] !== null ? substr($version['shipped_at'], 0, 10) : '' }}.
                @else
                    {{ $counts['done'] }} of {{ $counts['total'] }} {{ $counts['total'] === 1 ? 'story' : 'stories' }} delivered{{ $version['forecast_end'] !== null ? '; the last open one is forecast for '.$version['forecast_end'] : '' }}.
                @endif
                @if ($access->allows('git'))
                    Release branch <code>{{ $version['branch'] }}</code>.
                @endif
            </p>
        </div>
        <div class="page-actions">
            <a class="btn ghost" href="{{ route('larapilot.dashboard.index', $scopeLink) }}">@include('larapilot::dashboard.partials.icon', ['name' => 'board'])Board</a>
            <a class="btn ghost" href="{{ route('larapilot.dashboard.plan', $scopeLink) }}">@include('larapilot::dashboard.partials.icon', ['name' => 'plan'])Plan</a>
            @if ($access->allows('forecast'))
                <a class="btn ghost" href="{{ route('larapilot.dashboard.forecast', $scopeLink) }}">@include('larapilot::dashboard.partials.icon', ['name' => 'forecast'])Forecast</a>
            @endif
            <a class="btn ghost" href="{{ route('larapilot.dashboard.versions.notes', ['version' => $version['version']]) }}" title="The release notes of this version, as one Markdown file">@include('larapilot::dashboard.partials.icon', ['name' => 'download'])Release notes (.md)</a>
        </div>
    </header>

    <section class="metrics" aria-label="v{{ $version['version'] }} summary">
        <div class="card metric">
            <div class="metric-label">Delivered</div>
            <div class="metric-value">{{ $version['progress'] }}%</div>
            <div class="version-bar" role="img" aria-label="{{ $counts['done'] }} of {{ $counts['total'] }} stories done, {{ $counts['wip'] }} in progress or review">
                <span class="is-done" style="width: {{ $share($counts['done']) }}%"></span><span class="is-wip" style="width: {{ $share($counts['wip']) }}%"></span>
            </div>
        </div>
        <div class="card metric">
            <div class="metric-label">Stories</div>
            <div class="metric-value">{{ $counts['total'] }}</div>
            <div class="metric-note">{{ $counts['done'] }} done · {{ $counts['wip'] }} in progress or review · {{ $counts['todo'] }} to do</div>
        </div>
        <div class="card metric">
            <div class="metric-label">Story points</div>
            <div class="metric-value">{{ $version['points']['total'] }} SP</div>
            <div class="metric-note">{{ $version['points']['done'] }} done · {{ $version['points']['remaining'] }} open</div>
        </div>
        <div class="card metric">
            <div class="metric-label">{{ $version['shipped'] ? 'Milestone' : 'Forecast vs milestone' }}</div>
            <div class="metric-value">{{ $version['shipped'] ? (is_array($version['milestone']) ? $version['milestone']['date'] : '—') : ($version['forecast_end'] ?? '—') }}</div>
            <div class="metric-note">
                @if (is_array($version['milestone']))
                    {{ $version['milestone']['label'] !== '' ? $version['milestone']['label'].' · ' : '' }}{{ $version['shipped'] ? 'met' : 'due '.$version['milestone']['date'] }}{{ $version['risk'] !== null ? ' · '.($riskLabel[$version['risk']] ?? $version['risk']) : '' }}
                @else
                    No milestone names this version
                @endif
            </div>
        </div>
    </section>

    @if ($version['missing'] !== [])
        <section class="card version-panel">
            <p class="hint" style="margin: 0"><strong>The ledger names {{ count($version['missing']) }} {{ count($version['missing']) === 1 ? 'story' : 'stories' }} the backlog no longer has:</strong> {{ implode(', ', $version['missing']) }}. <code>php artisan larapilot:release-set --semver={{ $version['version'] }} --remove-spec=…</code> drops one.</p>
        </section>
    @endif

    <div class="version-columns">
        <section class="card version-panel">
            <h3>Stories</h3>
            <p class="hint">Open work first, in the order it is picked up; what is delivered closes the list.</p>
            @if ($version['stories'] === [])
                <p class="hint">No story targets this version yet. <code>/larapilot-release</code> → <em>Assign</em>, or <code>php artisan larapilot:release-set --semver={{ $version['version'] }} --add-spec=US-XXX</code>.</p>
            @else
                <div class="table-wrap">
                    <table class="version-table" id="version-stories">
                        <thead>
                            <tr>
                                <th>Story</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Epic</th>
                                <th class="num">SP</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($version['stories'] as $story)
                                <tr>
                                    <td><a href="{{ route('larapilot.dashboard.spec', ['code' => $story['code']]) }}"><code>{{ $story['code'] }}</code></a> {{ $story['title'] }}</td>
                                    <td>{{ $typeLabel[$story['type'] ?? ''] ?? '—' }}</td>
                                    <td><span class="badge {{ $views->statusBadge($story['status']) }}">{{ $story['status'] }}</span></td>
                                    <td>{{ $story['epic'] ?? '—' }}</td>
                                    <td class="num">{{ $story['points'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div>
            @if ($version['epics'] !== [])
                <section class="card version-panel">
                    <h3>Epics</h3>
                    <ul class="version-list">
                        @foreach ($version['epics'] as $epic)
                            <li><strong>{{ $epic['code'] ?? '' }}</strong> {{ $epic['title'] }} — {{ $epic['done'] }}/{{ $epic['specs'] }} · {{ $epic['points'] }} SP</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($version['deferred'] !== [])
                <section class="card version-panel">
                    <h3>Moved out</h3>
                    <p class="hint">Stories this version let go before it shipped.</p>
                    <ul class="version-list">
                        @foreach ($version['deferred'] as $item)
                            <li><a href="{{ route('larapilot.dashboard.spec', ['code' => $item['spec']]) }}"><code>{{ $item['spec'] }}</code></a> → {{ $item['to'] !== null ? 'v'.$item['to'] : 'backlog' }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>

    <section class="card version-panel" id="version-notes">
        <h3>Release notes</h3>
        <p class="hint">
            @if ($version['notes_written'])
                As the team wrote them, in <code>{{ app(\Larapilot\Services\ConfigService::class)->relativePath($version['notes_path']) }}</code>.
            @else
                Drafted from the stories: <code>php artisan larapilot:release-notes --semver={{ $version['version'] }} --write</code> keeps them, and <code>/larapilot-release</code> rewrites them in the words of the product.
            @endif
        </p>
        <div class="markdown version-notes">{!! \Larapilot\Support\Markdown::toHtml($version['notes_written'] ? (string) file_get_contents($version['notes_path']) : $version['notes']) !!}</div>
    </section>
@endsection
