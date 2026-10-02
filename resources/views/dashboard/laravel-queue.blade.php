@extends('larapilot::dashboard.layout')

@section('title', 'Queue · Laravel')

@push('styles')
@include('larapilot::dashboard.partials.laravel-styles')
@endpush

@section('content')
    @php
        $connection = $queue['connection'];
        $totals = $connection['totals'];
        $failed = $queue['failed'];
        $when = static fn (?int $time): string => $time ? \Illuminate\Support\Carbon::createFromTimestamp($time)->diffForHumans() : '—';
        $stateLabel = ['waiting' => 'Waiting', 'delayed' => 'Delayed', 'reserved' => 'Running'];
        $stateClass = ['waiting' => '', 'delayed' => 'is-warn', 'reserved' => 'is-busy'];
    @endphp

    <header class="page-head">
        <div>
            <h2>Laravel</h2>
            <p class="sub">The jobs that wait for a worker, the ones a worker holds, and the ones that failed. Nothing is retried or deleted from here: <code>php artisan queue:retry</code> and <code>queue:forget</code> do that.</p>
        </div>
    </header>

    @include('larapilot::dashboard.partials.laravel-tabs', ['current' => 'queue'])

    <div class="lv-bar" aria-label="Connections">
        @foreach ($queue['connections'] as $option)
            <a @class(['chip', 'current' => $option['current']]) href="{{ route('larapilot.dashboard.laravel.queue', $option['default'] ? [] : ['connection' => $option['name']]) }}" @if ($option['current']) aria-current="true" @endif>
                {{ $option['name'] }}@if ($option['driver'] !== '' && $option['driver'] !== $option['name']) <code>{{ $option['driver'] }}</code>@endif
                @if ($option['default'])
                    <span class="hint">default</span>
                @endif
            </a>
        @endforeach
    </div>

    <div class="metrics">
        <div class="card metric">
            <div class="metric-label">Waiting</div>
            <div class="metric-value">{{ $connection['error'] !== null ? '—' : number_format($totals['waiting']) }}</div>
            <div class="metric-note">Ready for a worker.</div>
        </div>
        <div class="card metric">
            <div class="metric-label">Delayed</div>
            <div class="metric-value">{{ $connection['error'] !== null ? '—' : number_format($totals['delayed']) }}</div>
            <div class="metric-note">Not due yet.</div>
        </div>
        <div class="card metric">
            <div class="metric-label">Running</div>
            <div class="metric-value">{{ $connection['error'] !== null ? '—' : number_format($totals['reserved']) }}</div>
            <div class="metric-note">Held by a worker.</div>
        </div>
        <div class="card metric lv-metric">
            <div class="metric-label">Failed</div>
            <div @class(['metric-value', 'is-alert' => (int) $failed['count'] > 0])>{{ $failed['count'] !== null ? number_format($failed['count']) : '—' }}</div>
            <div class="metric-note">{{ $failed['stored'] ? 'On every connection.' : 'Failed jobs are not kept.' }}</div>
        </div>
        @if ($queue['batches'] !== null)
            <div class="card metric">
                <div class="metric-label">Open batches</div>
                <div class="metric-value">{{ number_format($queue['batches']) }}</div>
                <div class="metric-note">Neither finished nor cancelled.</div>
            </div>
        @endif
    </div>

    <section class="card lv-panel" aria-labelledby="lv-jobs">
        <div class="lv-panel-head">
            <h3 id="lv-jobs">Jobs on <code>{{ $connection['name'] !== '' ? $connection['name'] : '—' }}</code></h3>
            @if ($connection['listed'] && count($connection['jobs']) >= $listed)
                <p class="hint">The first {{ $listed }}, in the order a worker takes them.</p>
            @endif
        </div>

        @if ($connection['error'] !== null)
            <p class="lv-error">{{ $connection['error'] }}</p>
            <p class="lv-note">The connection <code>{{ $connection['name'] }}</code> ({{ $connection['driver'] }}) did not answer.@if ($connection['driver'] === 'database') <code>php artisan queue:table</code> and <code>php artisan migrate</code> create its table.@endif</p>
        @elseif ($connection['note'] !== null)
            <p class="lv-note">{{ $connection['note'] }}</p>
        @else
            @if (count($connection['queues']) > 1 || ! $connection['listed'])
                <div class="table-wrap">
                    <table class="lv-table is-compact">
                        <thead>
                            <tr><th>Queue</th><th class="num">Waiting</th><th class="num">Delayed</th><th class="num">Running</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($connection['queues'] as $row)
                                <tr>
                                    <td><code>{{ $row['name'] }}</code></td>
                                    <td class="num">{{ number_format($row['waiting']) }}</td>
                                    <td class="num">{{ $row['delayed'] !== null ? number_format($row['delayed']) : '—' }}</td>
                                    <td class="num">{{ $row['reserved'] !== null ? number_format($row['reserved']) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if (! $connection['listed'])
                <p class="lv-note">The <code>{{ $connection['driver'] }}</code> driver says how many jobs wait, not which ones.</p>
            @elseif ($connection['jobs'] === [])
                <div class="empty">
                    <p>No job waits on this connection.</p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="lv-table">
                        <thead>
                            <tr><th>Job</th><th>Queue</th><th>State</th><th class="num">Attempts</th><th>Since</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($connection['jobs'] as $job)
                                <tr>
                                    <td>
                                        <span class="lv-job">{{ $job['job'] }}</span>
                                        @if ($job['id'] !== '')
                                            <small>#{{ $job['id'] }}</small>
                                        @endif
                                    </td>
                                    <td><code>{{ $job['queue'] }}</code></td>
                                    <td>
                                        <span class="lv-state {{ $stateClass[$job['state']] }}">{{ $stateLabel[$job['state']] }}</span>
                                        @if ($job['state'] === 'delayed' && $job['at'])
                                            <small>due {{ $when($job['at']) }}</small>
                                        @endif
                                    </td>
                                    <td class="num">{{ $job['attempts'] }}{{ $job['tries'] !== null ? ' / '.$job['tries'] : '' }}</td>
                                    <td class="nowrap">{{ $when($job['created']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($totals['waiting'] + $totals['delayed'] > 0)
                <p class="lv-note">A job leaves this list when a worker takes it: <code>php artisan queue:work{{ $connection['name'] !== $queue['default'] ? ' '.$connection['name'] : '' }}</code>.</p>
            @endif
        @endif
    </section>

    <section class="card lv-panel" id="failed" aria-labelledby="lv-failed">
        <div class="lv-panel-head">
            <h3 id="lv-failed">Failed jobs</h3>
            @if ($failed['count'] !== null && $failed['count'] > count($failed['jobs']))
                <p class="hint">The last {{ count($failed['jobs']) }} of {{ number_format($failed['count']) }}.</p>
            @endif
        </div>

        @if (! $failed['stored'])
            <p class="lv-note">The application does not keep the jobs that fail: <code>queue.failed.driver</code> is <code>null</code>.</p>
        @elseif ($failed['error'] !== null)
            <p class="lv-error">{{ $failed['error'] }}</p>
            <p class="lv-note">The failed jobs are kept by the <code>{{ $failed['driver'] }}</code> driver, which did not answer.</p>
        @elseif ($failed['jobs'] === [])
            <div class="empty">
                <p>No job failed.</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="lv-table">
                    <thead>
                        <tr><th>Job</th><th>Connection · queue</th><th>Failed</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($failed['jobs'] as $job)
                            <tr>
                                <td>
                                    <span class="lv-job">{{ $job['job'] }}</span>
                                    @if ($job['exception'] !== null)
                                        <small>{{ $job['exception'] }}</small>
                                    @endif
                                    @if ($job['id'] !== '')
                                        <small><code>php artisan queue:retry {{ $job['id'] }}</code></small>
                                    @endif
                                </td>
                                <td class="nowrap"><code>{{ $job['connection'] }}</code> · <code>{{ $job['queue'] }}</code></td>
                                <td class="nowrap">{{ $job['failed_at'] !== '' ? rescue(static fn () => \Illuminate\Support\Carbon::parse($job['failed_at'])->diffForHumans(), $job['failed_at'], false) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
