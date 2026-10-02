{{-- The Laravel page: one tab for each thing it reads. --}}
@php
    $current = $current ?? 'overview';
    $tabs = [
        'overview' => ['Overview', 'larapilot.dashboard.laravel'],
        'schedule' => ['Schedule', 'larapilot.dashboard.laravel.schedule'],
        'queue' => ['Queue', 'larapilot.dashboard.laravel.queue'],
        'mail' => ['Mail', 'larapilot.dashboard.laravel.mail'],
        'dumps' => ['Dumps', 'larapilot.dashboard.laravel.dumps'],
    ];
@endphp
<nav class="lv-tabs" aria-label="Laravel">
    @foreach ($tabs as $key => [$label, $route])
        <a href="{{ route($route) }}" @class(['is-current' => $current === $key]) @if ($current === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
