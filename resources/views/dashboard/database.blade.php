@extends('larapilot::dashboard.layout')

@section('title', 'Database')

@push('styles')
@include('larapilot::dashboard.partials.database-styles')
@endpush

@section('content')
    <header class="page-head">
        <div>
            <h2>Database</h2>
            <p class="sub">The tables of the application's database and what is in them — the connection in <code>.env</code>, read through Laravel, so the page is the same on MySQL, MariaDB, PostgreSQL, SQLite, and SQL Server. Nothing is changed from here.</p>
        </div>
        @if ($error === null)
            <form class="page-actions db-dump" method="get" action="{{ route('larapilot.dashboard.database.dump') }}">
                @if ($credentials_allowed)
                    <label class="db-check" title="Passwords, tokens, and secrets are left out unless this is ticked.">
                        <input type="checkbox" name="credentials" value="1">
                        Include passwords and tokens
                    </label>
                @else
                    <span class="hint">Passwords and tokens are left out on a shared host.</span>
                @endif
                <button type="submit" class="btn">@include('larapilot::dashboard.partials.icon', ['name' => 'download'])Download SQL</button>
                <details class="db-more" data-db-more>
                    <summary class="btn ghost">Other formats @include('larapilot::dashboard.partials.icon', ['name' => 'chevron'])</summary>
                    <div class="db-more-list">
                        <button type="submit" name="only" value="structure">
                            <strong>SQL, structure only</strong>
                            <small>The tables, their keys and indexes, and the views — no rows.</small>
                        </button>
                        <button type="submit" formaction="{{ route('larapilot.dashboard.database.migrations') }}">
                            <strong>Laravel migrations</strong>
                            <small>A .zip of migration files, one for each table, for <code>database/migrations</code>.</small>
                        </button>
                        <button type="submit" formaction="{{ route('larapilot.dashboard.database.seeders') }}">
                            <strong>Laravel seeders</strong>
                            <small>A .zip of seeder classes with every row, for <code>database/seeders</code>.</small>
                        </button>
                    </div>
                </details>
            </form>
        @endif
    </header>

    @include('larapilot::dashboard.partials.database-connection', ['connection' => $connection])

    @if ($error !== null)
        @include('larapilot::dashboard.partials.database-error', ['error' => $error, 'connection' => $connection])
    @else
        <div class="metrics">
            <div class="card metric">
                <div class="metric-label">Tables</div>
                <div class="metric-value">{{ number_format($tables) }}</div>
            </div>
            <div class="card metric">
                <div class="metric-label">Views</div>
                <div class="metric-value">{{ number_format($views) }}</div>
            </div>
            <div class="card metric">
                <div class="metric-label">Size</div>
                <div class="metric-value">{{ $size_label ?? '—' }}</div>
                @if ($size_label === null)
                    <div class="metric-note">The driver does not report it.</div>
                @endif
            </div>
        </div>

        @php
            // An empty database has nothing to draw, and every migration still to run.
            $view = ($view ?? 'tables') === 'diagram' && $objects === [] ? 'tables' : ($view ?? 'tables');
        @endphp

        <nav class="db-tabs" aria-label="Database">
            <a href="{{ route('larapilot.dashboard.database') }}" @if ($view === 'tables') aria-current="page" @endif>@include('larapilot::dashboard.partials.icon', ['name' => 'table'])Tables</a>
            @if ($objects !== [])
                <a href="{{ route('larapilot.dashboard.database', ['view' => 'diagram']) }}" @if ($view === 'diagram') aria-current="page" @endif>@include('larapilot::dashboard.partials.icon', ['name' => 'merge'])Diagram</a>
            @endif
            <a href="{{ route('larapilot.dashboard.database', ['view' => 'migrations']) }}" @if ($view === 'migrations') aria-current="page" @endif>@include('larapilot::dashboard.partials.icon', ['name' => 'layers'])Migrations</a>
        </nav>

        @if ($view === 'diagram')
            @include('larapilot::dashboard.partials.database-diagram')
        @elseif ($view === 'migrations')
            @include('larapilot::dashboard.partials.database-migrations', $migrations)
        @else
        <section class="card db-list" aria-label="Tables and views">
            @if ($objects === [])
                <div class="empty">
                    <p>The database has no tables yet. <code>php artisan migrate</code> creates them.</p>
                </div>
            @else
                <div class="db-list-tools" data-db-filter-wrap hidden>
                    <label class="field db-filter">
                        <input type="search" placeholder="Find a table…" autocomplete="off" data-db-filter aria-label="Find a table">
                    </label>
                </div>
                @php $sized = array_filter($objects, static fn (array $object): bool => $object['size_label'] !== null) !== []; @endphp
                <div class="db-list-head" aria-hidden="true">
                    <span>Name</span>
                    @if ($sized)
                        <span class="db-list-head-size">Size</span>
                    @endif
                </div>
                @foreach ($objects as $object)
                    <a class="db-list-row" href="{{ route('larapilot.dashboard.database.table', ['table' => $object['key']]) }}" data-db-item data-name="{{ strtolower($object['key']) }}">
                        <span class="db-list-name">
                            @include('larapilot::dashboard.partials.icon', ['name' => $object['kind'] === 'view' ? 'view' : 'table'])
                            <span class="db-list-label">
                                <span>@if ($schemas && $object['schema'] !== null)<span class="db-schema">{{ $object['schema'] }}.</span>@endif{{ $object['name'] }}</span>
                                @if ($object['comment'] !== null)
                                    <small>{{ $object['comment'] }}</small>
                                @endif
                            </span>
                            @if ($object['kind'] === 'view')
                                <span class="db-tag">View</span>
                            @endif
                        </span>
                        @if ($sized)
                            <span class="db-list-size">{{ $object['size_label'] ?? '—' }}</span>
                        @endif
                    </a>
                @endforeach
                <p class="db-list-empty" data-db-filter-empty hidden>No table matches.</p>
                <div class="db-list-foot">
                    {{ number_format($tables) }} {{ $tables === 1 ? 'table' : 'tables' }}@if ($views > 0) · {{ number_format($views) }} {{ $views === 1 ? 'view' : 'views' }}@endif
                </div>
            @endif
        </section>
        @endif
    @endif
@endsection

@push('scripts')
@include('larapilot::dashboard.partials.database-filter-script')
<script>
    (() => {
        // The list of the other downloads closes on a choice, a click outside, and Escape.
        document.querySelectorAll('[data-db-more]').forEach((menu) => {
            document.addEventListener('click', (event) => {
                if (!menu.contains(event.target) || event.target.closest('button')) {
                    setTimeout(() => {
                        menu.open = false;
                    }, 0);
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && menu.open) {
                    menu.open = false;
                    menu.querySelector('summary').focus();
                }
            });
        });
    })();
</script>
@endpush
