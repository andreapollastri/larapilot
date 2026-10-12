@extends('larapilot::dashboard.layout')

@section('title', 'Versions')

@push('styles')
<style>
    .versions-section { margin-bottom: 26px; }
    .versions-section > h3 { margin: 0 0 4px; font-size: 1.05rem; }
    .versions-section > .hint { margin: 0 0 12px; }
    .version-grid { display: grid; gap: 14px; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); }
    .version-card { padding: 16px 18px; display: flex; flex-direction: column; gap: 10px; min-width: 0; }
    .version-card.in-scope { border-color: color-mix(in srgb, var(--accent) 55%, var(--border)); box-shadow: 0 0 0 1px color-mix(in srgb, var(--accent) 35%, transparent); }
    .version-card-head { display: flex; justify-content: space-between; gap: 10px; align-items: flex-start; }
    .version-card-head h4 { margin: 0; font-size: 1.1rem; font-variant-numeric: tabular-nums; }
    .version-card-head h4 a { color: inherit; text-decoration: none; }
    .version-card-head h4 a:hover { text-decoration: underline; }
    .version-card-title { margin: 2px 0 0; color: var(--text-2); font-size: 0.86rem; }
    .version-card .chips { justify-content: flex-end; }
    .version-states { display: flex; height: 8px; border-radius: 999px; overflow: hidden; background: var(--surface-3); }
    .version-states span { display: block; height: 100%; }
    .version-states .is-done { background: var(--status-done); }
    .version-states .is-wip { background: var(--status-progress); }
    .version-figures { display: flex; flex-wrap: wrap; gap: 4px 14px; margin: 0; color: var(--text-2); font-size: 0.82rem; font-variant-numeric: tabular-nums; }
    .version-figures strong { color: var(--text); }
    .version-facts { display: grid; grid-template-columns: auto 1fr; gap: 3px 12px; margin: 0; font-size: 0.82rem; }
    .version-facts dt { color: var(--muted); }
    .version-facts dd { margin: 0; font-variant-numeric: tabular-nums; min-width: 0; overflow-wrap: anywhere; }
    .version-links { display: flex; flex-wrap: wrap; gap: 6px 12px; margin-top: auto; padding-top: 4px; font-size: 0.82rem; }
    .version-unassigned { padding: 16px 18px; }
    .version-unassigned.is-warn { border-color: color-mix(in srgb, var(--warn-fill) 50%, var(--border)); }
    .version-unassigned p { margin: 0 0 6px; }
    .version-codes { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
    .version-codes a { font-family: var(--mono); font-size: 0.78rem; }
</style>
@endpush

@section('content')
    @php
        $statusLabel = ['shipped' => 'Shipped', 'in_progress' => 'In progress', 'planned' => 'Planned'];
        $riskLabel = ['late' => 'Late', 'overdue' => 'Overdue', 'on_track' => 'On track'];
        $open = array_values(array_filter($rows, static fn (array $row): bool => ! $row['shipped']));
        $shipped = array_reverse(array_values(array_filter($rows, static fn (array $row): bool => $row['shipped'])));
        $selected = $scope['selected'] ?? [];
        $access = app(\Larapilot\Services\Auth\DashboardSession::class);
        $seesGit = $access->allows('git');
        $seesForecast = $access->allows('forecast');
        $nextMilestone = null;

        foreach ($open as $row) {
            if (is_array($row['milestone']) && ($nextMilestone === null || $row['milestone']['date'] < $nextMilestone['milestone']['date'])) {
                $nextMilestone = $row;
            }
        }

        $share = static fn (int $part, int $whole): int => $whole > 0 ? (int) round($part / $whole * 100) : 0;
    @endphp

    <header class="page-head">
        <div>
            <h2>Versions</h2>
            <p class="sub">Every version of the product: what it holds, what it has delivered, and when its last story is forecast to land against its milestone. A version opens on its stories and its release notes; its Board, Plan, and Forecast links keep the dashboard on it.</p>
        </div>
        @if ($rows !== [])
            <div class="page-actions">
                <a class="btn ghost" href="{{ route('larapilot.dashboard.roadmap') }}">@include('larapilot::dashboard.partials.icon', ['name' => 'roadmap'])Roadmap</a>
            </div>
        @endif
    </header>

    @if (! $enabled || $rows === [])
        <section class="card empty">
            @if (! $releaseMode)
                <p><strong>Release mode is off.</strong> Turn it on with <code>php artisan larapilot:settings-set --release-mode=YES</code>, then run <code>/larapilot-release</code>: every story gets a target version, and this page, the Board, the Plan, and the Forecast read the product version by version.</p>
            @else
                <p><strong>No version in the ledger yet.</strong> Run <code>/larapilot-release</code> to propose the release roadmap, or <code>php artisan larapilot:release-add --semver=0.1.0 --title="MVP"</code>; an existing project imports its tags with <code>php artisan larapilot:release-import</code>.</p>
            @endif
        </section>
    @else
        @include('larapilot::dashboard.partials.version-scope', ['scope' => $scope, 'scopeRoute' => 'larapilot.dashboard.versions'])

        <section class="metrics" aria-label="Versions summary">
            <div class="card metric">
                <div class="metric-label">Versions</div>
                <div class="metric-value">{{ count($rows) }}</div>
                <div class="metric-note">{{ count($shipped) }} shipped · {{ count(array_filter($open, static fn (array $row): bool => $row['current'])) }} in progress · {{ count(array_filter($open, static fn (array $row): bool => ! $row['current'])) }} planned</div>
            </div>
            <div class="card metric">
                <div class="metric-label">Working on</div>
                <div class="metric-value">{{ $current !== null ? 'v'.$current['version'] : '—' }}</div>
                <div class="metric-note">
                    @switch($current['why'] ?? null)
                        @case('branch') Its release branch is checked out @break
                        @case('only_in_progress') The one version in progress @break
                        @case('oldest_in_progress') The oldest version in progress @break
                        @case('oldest_planned') Nothing in progress: the next planned @break
                        @default Every version is shipped
                    @endswitch
                </div>
            </div>
            <div class="card metric">
                <div class="metric-label">Next milestone</div>
                <div class="metric-value">{{ $nextMilestone !== null ? $nextMilestone['milestone']['date'] : '—' }}</div>
                <div class="metric-note">
                    @if ($nextMilestone !== null)
                        v{{ $nextMilestone['version'] }}{{ $nextMilestone['milestone']['label'] !== '' ? ' · '.$nextMilestone['milestone']['label'] : '' }}{{ $nextMilestone['risk'] !== null ? ' · '.($riskLabel[$nextMilestone['risk']] ?? $nextMilestone['risk']) : '' }}
                    @else
                        No milestone names an open version
                    @endif
                </div>
            </div>
            <div class="card metric">
                <div class="metric-label">No version yet</div>
                <div class="metric-value">{{ $unassigned['count'] }}</div>
                <div class="metric-note">{{ $unassigned['count'] === 1 ? 'open story' : 'open stories' }} · {{ $unassigned['points'] }} SP no version claims</div>
            </div>
        </section>

        @if ($unassigned['count'] > 0)
            <section @class(['card', 'version-unassigned', 'versions-section', 'is-warn' => $releaseMode])>
                <p><strong>{{ $unassigned['count'] }} open {{ $unassigned['count'] === 1 ? 'story targets' : 'stories target' }} no version.</strong>
                    @if ($releaseMode)
                        With release mode on, every story targets one: <code>/larapilot-release</code> → <em>Assign</em>, or <code>php artisan larapilot:release-set --semver=x.y.z --add-spec=US-XXX</code>. Implement asks for it before it starts one.
                    @else
                        They follow the classic flow.
                    @endif
                </p>
                <div class="version-codes">
                    @foreach (array_slice($unassigned['specs'], 0, 40) as $code)
                        <a href="{{ route('larapilot.dashboard.spec', ['code' => $code]) }}">{{ $code }}</a>
                    @endforeach
                    @if (count($unassigned['specs']) > 40)
                        <span class="hint">and {{ count($unassigned['specs']) - 40 }} more</span>
                    @endif
                </div>
                <p class="hint" style="margin-top: 8px"><a href="{{ route('larapilot.dashboard.index', ['version' => \Larapilot\Services\VersionService::NONE]) }}">See them on the Board</a></p>
            </section>
        @endif

        @foreach ([['Open', 'In progress first, then planned, oldest version first.', $open], ['Shipped', 'Newest first.', $shipped]] as [$heading, $hint, $list])
            @continue($list === [])
            <section class="versions-section">
                <h3>{{ $heading }}</h3>
                <p class="hint">{{ $hint }}</p>
                <div class="version-grid">
                    @foreach ($list as $row)
                        @php $scopeLink = ['version' => $row['version']]; @endphp
                        <article @class(['card', 'version-card', 'in-scope' => in_array($row['version'], $selected, true)]) id="v{{ $row['version'] }}">
                            <div class="version-card-head">
                                <div>
                                    <h4><a href="{{ route('larapilot.dashboard.versions.show', ['version' => $row['version']]) }}">v{{ $row['version'] }}</a></h4>
                                    @if ($row['title'] !== '')
                                        <p class="version-card-title">{{ $row['title'] }}</p>
                                    @endif
                                </div>
                                <div class="chips">
                                    <span @class(['chip', 'current' => $row['current'], 'live' => $row['shipped']])>{{ $statusLabel[$row['status']] ?? $row['status'] }}</span>
                                    @if ($row['risk'] === 'late' || $row['risk'] === 'overdue')
                                        <span class="chip stale">{{ $riskLabel[$row['risk']] }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="version-states" role="img" aria-label="{{ $row['counts']['done'] }} of {{ $row['counts']['total'] }} stories done, {{ $row['counts']['wip'] }} in progress or review">
                                <span class="is-done" style="width: {{ $share($row['counts']['done'], $row['counts']['total']) }}%"></span><span class="is-wip" style="width: {{ $share($row['counts']['wip'], $row['counts']['total']) }}%"></span>
                            </div>
                            <p class="version-figures">
                                <span><strong>{{ $row['progress'] }}%</strong> delivered</span>
                                <span>{{ $row['counts']['done'] }}/{{ $row['counts']['total'] }} stories</span>
                                <span>{{ $row['points']['done'] }}/{{ $row['points']['total'] }} SP</span>
                                @if ($row['counts']['wip'] > 0)
                                    <span>{{ $row['counts']['wip'] }} in progress</span>
                                @endif
                            </p>
                            <dl class="version-facts">
                                @if ($row['shipped'])
                                    <dt>Shipped</dt><dd>{{ $row['shipped_at'] !== null ? substr($row['shipped_at'], 0, 10) : '—' }}</dd>
                                @else
                                    <dt>Forecast</dt><dd>{{ $row['forecast_end'] ?? ($row['counts']['total'] === 0 ? 'No story yet' : '—') }}</dd>
                                @endif
                                @if (is_array($row['milestone']))
                                    <dt>Milestone</dt><dd>{{ $row['milestone']['date'] }}{{ $row['milestone']['label'] !== '' ? ' · '.$row['milestone']['label'] : '' }}</dd>
                                @endif
                                @if ($seesGit)
                                    <dt>Branch</dt><dd><code>{{ $row['branch'] }}</code></dd>
                                @endif
                                @if ($row['deferred'] !== [])
                                    <dt>Moved out</dt><dd>{{ count($row['deferred']) }} {{ count($row['deferred']) === 1 ? 'story' : 'stories' }}</dd>
                                @endif
                            </dl>
                            <nav class="version-links" aria-label="v{{ $row['version'] }} across the dashboard">
                                <a href="{{ route('larapilot.dashboard.index', $scopeLink) }}">Board</a>
                                <a href="{{ route('larapilot.dashboard.plan', $scopeLink) }}">Plan</a>
                                @if ($seesForecast)
                                    <a href="{{ route('larapilot.dashboard.forecast', $scopeLink) }}">Forecast</a>
                                @endif
                                <a href="{{ route('larapilot.dashboard.versions.notes', ['version' => $row['version']]) }}">Release notes</a>
                            </nav>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif
@endsection
