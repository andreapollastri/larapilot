@extends('larapilot::dashboard.layout')

@section('title', 'Laravel')

@push('styles')
@include('larapilot::dashboard.partials.laravel-styles')
@endpush

@section('content')
    @php
        $tasks = $schedule['tasks'];
        $next = collect($tasks)->first(static fn (array $task): bool => $task['runs_here'] && $task['next_at'] !== null);
        $connection = $queue['connection'];
        $totals = $connection['totals'];
        $failed = $queue['failed'];
    @endphp

    <header class="page-head">
        <div>
            <h2>Laravel</h2>
            <p class="sub">How the framework is set up in this application and what it is doing: the drivers in use, what is cached, the scheduled tasks, the jobs that wait, and the mail and the dumps it produced. Read live; nothing is changed from here.</p>
        </div>
    </header>

    @include('larapilot::dashboard.partials.laravel-tabs', ['current' => 'overview'])

    <div class="metrics">
        <a class="card metric lv-metric" href="{{ route('larapilot.dashboard.laravel.schedule') }}">
            <div class="metric-label">Scheduled tasks</div>
            <div class="metric-value">{{ $schedule['error'] !== null ? '—' : number_format(count($tasks)) }}</div>
            <div class="metric-note">
                @if ($schedule['error'] !== null)
                    The schedule could not be read.
                @elseif ($next !== null)
                    Next: {{ $next['command'] }}, {{ $next['next_human'] }}
                @else
                    Nothing is scheduled.
                @endif
            </div>
        </a>
        <a class="card metric lv-metric" href="{{ route('larapilot.dashboard.laravel.queue') }}">
            <div class="metric-label">Jobs waiting</div>
            <div class="metric-value">{{ $connection['error'] !== null ? '—' : number_format($totals['waiting'] + $totals['delayed']) }}</div>
            <div class="metric-note">
                @if ($connection['error'] !== null)
                    The queue did not answer.
                @elseif ($connection['note'] !== null)
                    The <code>{{ $connection['driver'] }}</code> driver holds no job.
                @else
                    On <code>{{ $connection['name'] }}</code>{{ $totals['delayed'] > 0 ? ' · '.number_format($totals['delayed']).' delayed' : '' }}{{ $totals['reserved'] > 0 ? ' · '.number_format($totals['reserved']).' running' : '' }}
                @endif
            </div>
        </a>
        <a class="card metric lv-metric" href="{{ route('larapilot.dashboard.laravel.queue') }}#failed">
            <div class="metric-label">Failed jobs</div>
            <div @class(['metric-value', 'is-alert' => (int) $failed['count'] > 0])>{{ $failed['count'] !== null ? number_format($failed['count']) : '—' }}</div>
            <div class="metric-note">
                @if (! $failed['stored'])
                    Failed jobs are not kept.
                @elseif ($failed['error'] !== null)
                    The failed jobs could not be read.
                @elseif ($failed['count'] === 0)
                    None failed.
                @else
                    Last: {{ $failed['jobs'][0]['job'] ?? '—' }}
                @endif
            </div>
        </a>
        <a class="card metric lv-metric" href="{{ route('larapilot.dashboard.laravel.mail') }}">
            <div class="metric-label">Mail kept</div>
            <div class="metric-value">{{ number_format($recorded['mail']) }}</div>
            <div class="metric-note">{{ $recorded['mail_recording'] ? 'Every mail sent is kept.' : 'Not kept here.' }}</div>
        </a>
        <a class="card metric lv-metric" href="{{ route('larapilot.dashboard.laravel.dumps') }}">
            <div class="metric-label">Dumps kept</div>
            <div class="metric-value">{{ number_format($recorded['dumps']) }}</div>
            <div class="metric-note">{{ $recorded['dumps_recording'] ? 'Every dump() and dd() is kept.' : 'Not kept here.' }}</div>
        </a>
    </div>

    <div class="lv-grid">
        <section class="card lv-panel" aria-labelledby="lv-drivers">
            <div class="lv-panel-head">
                <h3 id="lv-drivers">Drivers</h3>
                <p class="hint">What <code>.env</code> and <code>config/</code> resolve to now.</p>
            </div>
            <ul class="lv-rows">
                @foreach ($drivers as $driver)
                    <li class="lv-row">
                        <span class="lv-row-label">{{ $driver['service'] }}</span>
                        <div class="lv-row-body">
                            <div class="lv-row-main">
                                <strong>{{ $driver['name'] !== '' ? $driver['name'] : '—' }}</strong>
                                @if ($driver['driver'] !== '' && $driver['driver'] !== $driver['name'])
                                    <span class="lv-tag">{{ $driver['driver'] }}</span>
                                @endif
                            </div>
                            @if ($driver['facts'] !== [])
                                <span class="hint">{{ implode(' · ', $driver['facts']) }}</span>
                            @endif
                            @if ($driver['note'] !== null)
                                <span class="lv-warn">{{ $driver['note'] }}</span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="card lv-panel" aria-labelledby="lv-caches">
            <div class="lv-panel-head">
                <h3 id="lv-caches">Caches</h3>
                <p class="hint"><code>php artisan optimize:clear</code> empties them all.</p>
            </div>
            <ul class="lv-rows">
                @foreach ($caches as $cache)
                    <li class="lv-row">
                        <span class="lv-row-label">{{ $cache['label'] }}</span>
                        <div class="lv-row-body">
                            <div class="lv-row-main">
                                <span @class(['lv-state', 'is-on' => $cache['cached'], 'is-bad' => $cache['key'] === 'application' && ! $cache['cached']])>{{ $cache['state'] }}</span>
                            </div>
                            <span class="hint">{{ $cache['detail'] }}</span>
                            @if ($cache['warning'] !== null)
                                <span class="lv-warn">{{ $cache['warning'] }}</span>
                            @endif
                            <span class="hint">
                                @if ($cache['build'] !== null)
                                    <code>php artisan {{ $cache['build'] }}</code> builds it ·
                                @endif
                                <code>php artisan {{ $cache['clear'] }}</code> empties it
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
@endsection
