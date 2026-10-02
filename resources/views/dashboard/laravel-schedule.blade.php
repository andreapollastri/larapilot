@extends('larapilot::dashboard.layout')

@section('title', 'Schedule · Laravel')

@push('styles')
@include('larapilot::dashboard.partials.laravel-styles')
@endpush

@section('content')
    @php
        $tasks = $schedule['tasks'];
        $every = static function (int $seconds): string {
            return $seconds === 1 ? 'every second' : 'every '.$seconds.' seconds';
        };
    @endphp

    <header class="page-head">
        <div>
            <h2>Laravel</h2>
            <p class="sub">The tasks of the scheduler, the next one due first — what <code>php artisan schedule:list</code> prints. They run when the cron of the server calls <code>php artisan schedule:run</code> every minute, or while <code>php artisan schedule:work</code> is open: the page cannot tell whether either does.</p>
        </div>
    </header>

    @include('larapilot::dashboard.partials.laravel-tabs', ['current' => 'schedule'])

    <div class="lv-bar">
        <span class="chip current">@include('larapilot::dashboard.partials.icon', ['name' => 'clock']){{ number_format(count($tasks)) }} {{ count($tasks) === 1 ? 'task' : 'tasks' }}</span>
        <span class="chip">Timezone <code>{{ $schedule['timezone'] }}</code></span>
    </div>

    <section class="card lv-panel" aria-label="Scheduled tasks">
        @if ($schedule['error'] !== null)
            <div class="lv-panel-head"><h3>The schedule could not be read</h3></div>
            <p class="lv-error">{{ $schedule['error'] }}</p>
            <p class="lv-note"><code>php artisan schedule:list</code> reads it from the console.</p>
        @elseif ($tasks === [])
            <div class="empty">
                <p>No task is scheduled. <code>Schedule::command('…')->daily();</code> in <code>routes/console.php</code> adds one.</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="lv-table">
                    <thead>
                        <tr><th>Task</th><th>When</th><th>Next run</th><th>Options</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($tasks as $task)
                            <tr @class(['is-off' => ! $task['runs_here']])>
                                <td>
                                    <span class="lv-job">{{ $task['command'] }}</span>
                                    @if ($task['where'] !== null)
                                        <small>at @if ($task['where_link'] !== null)<a href="{{ $task['where_link'] }}"><code>{{ $task['where'] }}</code></a>@else<code>{{ $task['where'] }}</code>@endif</small>
                                    @endif
                                    @if ($task['description'] !== null)
                                        <small>{{ $task['description'] }}</small>
                                    @endif
                                </td>
                                <td class="nowrap">
                                    <code>{{ $task['expression'] }}</code>
                                    @if ($task['repeat'] !== null)
                                        <small>{{ $every($task['repeat']) }}</small>
                                    @endif
                                    @if ($task['timezone'] !== null && $task['timezone'] !== $schedule['timezone'])
                                        <small>{{ $task['timezone'] }}</small>
                                    @endif
                                </td>
                                <td class="nowrap">
                                    @if (! $task['runs_here'])
                                        <span class="lv-state">Not in {{ app()->environment() }}</span>
                                    @elseif ($task['next_at'] !== null)
                                        {{ $task['next_human'] }}
                                        <small>{{ $task['next_label'] }}</small>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    <div class="lv-tags">
                                        @if ($task['kind'] === 'callback')
                                            <span class="lv-tag">Runs in PHP</span>
                                        @elseif ($task['kind'] === 'shell')
                                            <span class="lv-tag">Shell command</span>
                                        @endif
                                        @if ($task['without_overlapping'])
                                            <span class="lv-tag" title="A run is skipped while the one before is still going.">No overlap</span>
                                        @endif
                                        @if ($task['one_server'])
                                            <span class="lv-tag" title="Only one server of the cluster runs it.">One server</span>
                                        @endif
                                        @if ($task['background'])
                                            <span class="lv-tag" title="The scheduler does not wait for it to end.">Background</span>
                                        @endif
                                        @if ($task['maintenance'])
                                            <span class="lv-tag" title="Runs while the application is down for maintenance.">In maintenance</span>
                                        @endif
                                        @foreach ($task['environments'] as $environment)
                                            <span @class(['lv-tag', 'is-off' => ! $task['runs_here']]) title="Runs only in this environment.">{{ $environment }}</span>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
