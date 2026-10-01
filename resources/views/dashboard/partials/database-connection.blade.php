{{-- Where the viewer reads from: driver, connection, database, host. Never a password. --}}
<div class="chips db-conn" aria-label="Connection">
    <span class="chip current">@include('larapilot::dashboard.partials.icon', ['name' => 'database']){{ $connection['driver_label'] }}</span>
    <span class="chip">Connection <code>{{ $connection['name'] }}</code></span>
    @if ($connection['database'] !== '')
        <span class="chip">{{ $connection['driver'] === 'sqlite' ? 'File' : 'Database' }} <code>{{ $connection['database'] }}</code></span>
    @endif
    @if ($connection['host'] !== null)
        <span class="chip">Host <code>{{ $connection['host'] }}</code></span>
    @endif
</div>
