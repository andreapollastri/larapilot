@extends('larapilot::dashboard.layout')

@section('title', 'Forecast')

@push('styles')
<style>
    .forecast-panel { margin-bottom: 18px; }

    .forecast-panel h3 {
        margin: 0 0 4px;
        font-size: 1rem;
    }

    .forecast-panel .hint { margin: 0 0 14px; }
    .forecast-panel .hint:last-child { margin-bottom: 0; }

    .metric .sp-bar {
        display: flex;
        height: 6px;
        margin-top: 10px;
        border-radius: 999px;
        background: var(--surface-3);
        overflow: hidden;
    }

    .metric .sp-bar > span { display: block; height: 100%; }
    .metric .sp-bar .is-done { background: var(--ok-fill); }
    .metric .sp-bar .is-wip { background: var(--accent); }

    .metric .metric-aside {
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid var(--border);
        color: var(--text-2);
        font-size: 0.78rem;
        line-height: 1.4;
    }

    .metric .metric-aside strong { font-variant-numeric: tabular-nums; }

    .compare {
        width: 100%;
        min-width: 420px;
        border-collapse: collapse;
        font-size: 0.9rem;
    }

    .compare th,
    .compare td {
        padding: 10px 12px;
        border-bottom: 1px solid var(--border);
        text-align: right;
        vertical-align: middle;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .compare th:first-child,
    .compare td:first-child { text-align: left; color: var(--text-2); white-space: normal; }

    .compare thead th {
        color: var(--muted);
        font-size: 0.7rem;
        font-weight: 650;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .compare thead th small {
        display: block;
        margin-top: 3px;
        font-size: 0.68rem;
        font-weight: 500;
        letter-spacing: 0.02em;
        text-transform: none;
    }

    .compare tbody tr:last-child td { border-bottom: 0; }
    .compare td.is-lead { color: var(--accent-strong); font-weight: 650; }
    .compare tbody tr:hover { background: var(--surface-2); }

    .assumptions {
        margin: 14px 0 0;
        padding: 14px 0 0 18px;
        border-top: 1px solid var(--border);
        color: var(--text-2);
        font-size: 0.85rem;
        line-height: 1.55;
    }

    .assumptions li { margin: 4px 0; }
    .assumptions code { font-size: 0.8em; }

    .epics {
        width: 100%;
        min-width: 620px;
        border-collapse: collapse;
        font-size: 0.84rem;
    }

    .epics th,
    .epics td {
        padding: 10px 12px;
        border-bottom: 1px solid var(--border);
        text-align: right;
        vertical-align: top;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .epics th {
        color: var(--muted);
        font-size: 0.7rem;
        font-weight: 650;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .epics th:first-child,
    .epics td:first-child { min-width: 180px; text-align: left; white-space: normal; }

    .epics tbody tr:hover { background: var(--surface-2); }
    .epics .epic-title { display: block; margin-top: 2px; color: var(--text-2); }
    .epics .is-blank { color: var(--muted); }
    .epics tfoot td { color: var(--text); font-weight: 650; border-bottom: 0; }
    .forecast-panel .epics-note { margin: 14px 0 0; }
</style>
@endpush

@section('content')
    @php
        $specs = is_array($specs ?? null) ? $specs : ['total' => 0, 'done' => 0, 'wip' => 0, 'todo' => 0, 'unsized' => 0];
        $points = is_array($points ?? null) ? $points : ['total' => 0, 'done' => 0, 'wip' => 0, 'todo' => 0, 'remaining' => 0];
        $human = is_array($human ?? null) ? $human : ['hours_total' => 0.0, 'hours_remaining' => 0.0, 'sprints_total' => 0, 'sprints_remaining' => 0];
        $ai = is_array($ai ?? null) ? $ai : ['basis' => 'model', 'optimistic' => [], 'realistic' => []];
        $hoursPerPoint = is_array($hours_per_point ?? null) ? $hours_per_point : ['value' => 0.0, 'source' => 'settings', 'effort' => 'STANDARD', 'planned_specs' => 0];
        $sprint = is_array($sprint ?? null) ? $sprint : ['hours_per_day' => 6.0, 'days_per_week' => 5, 'weeks' => 2, 'hours' => 60.0, 'source' => 'default'];
        $measured = is_array($measured ?? null) ? $measured : null;
        $epics = is_array($epics ?? null) ? $epics : [];
        $assumptions = is_array($assumptions ?? null) ? $assumptions : [];
        $isMeasured = ($ai['basis'] ?? 'model') === 'measured';
        $realistic = is_array($ai['realistic'] ?? null) ? $ai['realistic'] : [];
        $optimistic = is_array($ai['optimistic'] ?? null) ? $ai['optimistic'] : [];
        $n = fn (float|int|null $value): string => \Larapilot\Services\ForecastService::number((float) ($value ?? 0));
        $times = fn (float|int|null $value): string => \Larapilot\Services\ForecastService::times($value === null ? null : (float) $value);
        $share = fn (int $part): int => ($points['total'] ?? 0) > 0 ? (int) round($part / $points['total'] * 100) : 0;
    @endphp

    <header class="page-head">
        <div>
            <h2>Forecast</h2>
            <p class="sub">How long the backlog takes: one person on two-week sprints, and the same work with Larapilot. The points and statuses are the Board's; the hours a point stands for are the ones <a href="{{ route('larapilot.dashboard.economics') }}">Economics</a> prices with.</p>
        </div>
        <div class="page-actions">
            <a class="btn ghost" href="{{ route('larapilot.dashboard.forecast.report') }}">@include('larapilot::dashboard.partials.icon', ['name' => 'download'])Download report.md</a>
        </div>
    </header>

    @if (($specs['total'] ?? 0) === 0)
        <section class="card">
            <div class="empty">
                <p><strong>No specs in the backlog yet.</strong> Once the backlog holds user stories with story points, this page says how many hours and sprints they take for one person, and what the same work takes with Larapilot.</p>
            </div>
        </section>
    @else
        <div class="metrics">
            <div class="card metric">
                <div class="metric-label">Story points</div>
                <div class="metric-value">{{ $points['total'] }} SP</div>
                <div class="metric-note">{{ $points['done'] }} done · {{ $points['wip'] }} in progress or review · {{ $points['todo'] }} to do</div>
                <div class="sp-bar" role="img" aria-label="{{ $share($points['done']) }}% of the story points done, {{ $share($points['wip']) }}% in progress or review">
                    <span class="is-done" style="width: {{ $share($points['done']) }}%"></span><span class="is-wip" style="width: {{ $share($points['wip']) }}%"></span>
                </div>
            </div>
            <div class="card metric">
                <div class="metric-label">Sprints · 1 person</div>
                <div class="metric-value">{{ $human['sprints_total'] }}</div>
                <div class="metric-note">{{ $human['sprints_remaining'] }} remaining · two weeks of {{ $n($sprint['hours']) }} h each</div>
            </div>
            <div class="card metric">
                <div class="metric-label">Hours per story point</div>
                <div class="metric-value">{{ $n($hoursPerPoint['value']) }} h</div>
                <div class="metric-note">
                    @if (($hoursPerPoint['source'] ?? 'settings') === 'plans')
                        From {{ $hoursPerPoint['planned_specs'] }} planned {{ $hoursPerPoint['planned_specs'] === 1 ? 'spec' : 'specs' }}, for one person
                    @else
                        Assumed · effort {{ $hoursPerPoint['effort'] }}, for one person
                    @endif
                </div>
                @if ($measured !== null)
                    <div class="metric-aside">With Larapilot: <strong>{{ $n($measured['hours_per_point']) }} h</strong> per point, measured on {{ $measured['specs'] }} delivered {{ $measured['specs'] === 1 ? 'spec' : 'specs' }}</div>
                @endif
            </div>
            <div class="card metric">
                <div class="metric-label">Hours · 1 person</div>
                <div class="metric-value">{{ $n($human['hours_total']) }} h</div>
                <div class="metric-note">{{ $n($human['hours_remaining']) }} h remaining · {{ $points['remaining'] }} SP still open</div>
            </div>
        </div>

        <section class="card panel forecast-panel" id="compare-panel">
            <h3>Human vs AI</h3>
            <p class="hint">
                @if ($isMeasured)
                    One person without an agent, beside what Larapilot took per point on the delivered specs of this project.
                    @if (! empty($measured['thin']))
                        The measure rests on {{ $measured['specs'] }} {{ $measured['specs'] === 1 ? 'spec' : 'specs' }}: a first reading, not a rate.
                    @endif
                @else
                    One person without an agent, beside a model of Larapilot with a person reviewing and approving. No delivered spec has hours in the ledger yet, so the AI side is a range, not a measure.
                @endif
            </p>
            <div class="table-wrap">
            <table class="compare" id="compare-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>Human<small>one person</small></th>
                        @if ($isMeasured)
                            <th>AI<small>measured</small></th>
                        @else
                            <th>AI optimistic<small>{{ $n(\Larapilot\Services\ForecastService::AI_SPEEDUP['optimistic']) }}× build · {{ $n(\Larapilot\Services\ForecastService::AI_OVERHEAD_HOURS_PER_SPEC['optimistic']) }} h a spec</small></th>
                            <th>AI realistic<small>{{ $n(\Larapilot\Services\ForecastService::AI_SPEEDUP['realistic']) }}× build · {{ $n(\Larapilot\Services\ForecastService::AI_OVERHEAD_HOURS_PER_SPEC['realistic']) }} h a spec</small></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Hours</td>
                        <td>{{ $n($human['hours_total']) }} h</td>
                        @unless ($isMeasured)
                            <td>{{ $n($optimistic['hours_total'] ?? 0) }} h</td>
                        @endunless
                        <td class="is-lead">{{ $n($realistic['hours_total'] ?? 0) }} h</td>
                    </tr>
                    <tr>
                        <td>Hours remaining</td>
                        <td>{{ $n($human['hours_remaining']) }} h</td>
                        @unless ($isMeasured)
                            <td>{{ $n($optimistic['hours_remaining'] ?? 0) }} h</td>
                        @endunless
                        <td class="is-lead">{{ $n($realistic['hours_remaining'] ?? 0) }} h</td>
                    </tr>
                    <tr>
                        <td>Sprints</td>
                        <td>{{ $human['sprints_total'] }}</td>
                        @unless ($isMeasured)
                            <td>{{ $optimistic['sprints_total'] ?? 0 }}</td>
                        @endunless
                        <td class="is-lead">{{ $realistic['sprints_total'] ?? 0 }}</td>
                    </tr>
                    <tr>
                        <td>Sprints remaining</td>
                        <td>{{ $human['sprints_remaining'] }}</td>
                        @unless ($isMeasured)
                            <td>{{ $optimistic['sprints_remaining'] ?? 0 }}</td>
                        @endunless
                        <td class="is-lead">{{ $realistic['sprints_remaining'] ?? 0 }}</td>
                    </tr>
                    <tr>
                        <td>Speed-up</td>
                        <td>1×</td>
                        @unless ($isMeasured)
                            <td>{{ $times($optimistic['speedup'] ?? null) }}</td>
                        @endunless
                        <td class="is-lead">{{ $times($realistic['speedup'] ?? null) }}</td>
                    </tr>
                </tbody>
            </table>
            </div>
            <ul class="assumptions" id="forecast-assumptions">
                @foreach ($assumptions as $assumption)
                    <li>{{ $assumption }}</li>
                @endforeach
            </ul>
        </section>

        <section class="card panel forecast-panel" id="epics-panel">
            <h3>By epic</h3>
            <p class="hint">Story points, hours for one person, hours with Larapilot ({{ $isMeasured ? 'measured' : 'the realistic case' }}), and the sprints one person needs. The specs without an epic close the list.</p>
            <div class="table-wrap">
            <table class="epics" id="epics-table">
                <thead>
                    <tr>
                        <th>Epic</th>
                        <th>Specs</th>
                        <th>SP</th>
                        <th>Done</th>
                        <th>Human</th>
                        <th>AI {{ $isMeasured ? 'measured' : 'realistic' }}</th>
                        <th>Sprints</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($epics as $epic)
                        <tr>
                            <td>
                                @if ($epic['code'] !== null)
                                    <strong>{{ $epic['code'] }}</strong>
                                    <span class="epic-title">{{ $epic['title'] }}</span>
                                @else
                                    <span class="is-blank">{{ $epic['title'] }}</span>
                                @endif
                            </td>
                            <td>{{ $epic['specs'] }}</td>
                            <td @class(['is-blank' => $epic['points'] === 0])>{{ $epic['points'] }}</td>
                            <td @class(['is-blank' => $epic['points_done'] === 0])>{{ $epic['points_done'] }}</td>
                            <td @class(['is-blank' => $epic['points'] === 0])>{{ $n($epic['human_hours']) }} h</td>
                            <td @class(['is-blank' => $epic['points'] === 0])>{{ $n($epic['ai_hours']) }} h</td>
                            <td @class(['is-blank' => $epic['points'] === 0])>{{ $epic['sprints'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td>Whole backlog</td>
                        <td>{{ $specs['total'] }}</td>
                        <td>{{ $points['total'] }}</td>
                        <td>{{ $points['done'] }}</td>
                        <td>{{ $n($human['hours_total']) }} h</td>
                        <td>{{ $n($realistic['hours_total'] ?? 0) }} h</td>
                        <td>{{ $human['sprints_total'] }}</td>
                    </tr>
                </tfoot>
            </table>
            </div>
            <p class="hint epics-note">Sprints are counted epic by epic and rounded up, so their sum can exceed the sprints of the whole backlog.</p>
        </section>
    @endif
@endsection
