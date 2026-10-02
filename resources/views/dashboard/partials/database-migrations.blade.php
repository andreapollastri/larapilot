{{-- Which migrations ran and which are still to run, as `migrate:status` tells it. Read by DatabaseMigrationService. --}}
@push('styles')
<style>
    .db-mig-top {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px 14px;
        padding: 12px 14px;
        border-bottom: 1px solid var(--border);
    }

    .db-mig-counts { display: flex; flex-wrap: wrap; gap: 6px; }
    .db-mig-top .hint { margin: 0; }
    .db-mig-top .db-filter { flex: 1 1 220px; min-width: 0; margin-left: auto; max-width: 320px; }

    .db-mig-head,
    .db-mig-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 4px 12px;
        align-items: center;
        padding: 10px 14px;
    }

    .db-mig-head {
        border-bottom: 1px solid var(--border);
        color: var(--muted);
        font-size: 0.7rem;
        font-weight: 650;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .db-mig-head span:nth-child(2), .db-mig-head span:nth-child(3) { display: none; }
    .db-mig-row { border-top: 1px solid var(--border); }
    .db-mig-head + .db-mig-row { border-top: 0; }
    .db-mig-row.is-pending { background: color-mix(in srgb, var(--status-progress) 6%, transparent); }

    .db-mig-name { min-width: 0; }
    .db-mig-name strong { display: block; color: var(--text); font-weight: 550; overflow-wrap: anywhere; }

    .db-mig-name code {
        display: block;
        padding: 0;
        background: transparent;
        color: var(--muted);
        font-size: 0.74rem;
        overflow-wrap: anywhere;
    }

    .db-mig-name .db-tag { display: inline-block; margin-top: 4px; text-transform: none; letter-spacing: 0; }
    .db-mig-date, .db-mig-batch { color: var(--muted); font-size: 0.82rem; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .db-mig-date { grid-column: 1; }
    .db-mig-batch { grid-column: 2; grid-row: 2; text-align: right; }
    .db-mig-state { grid-column: 2; grid-row: 1; justify-self: end; }

    @media (min-width: 760px) {
        .db-mig-head,
        .db-mig-row { grid-template-columns: minmax(0, 1fr) 110px 80px 150px; padding: 9px 16px; }

        .db-mig-head span:nth-child(2), .db-mig-head span:nth-child(3) { display: block; }
        .db-mig-date, .db-mig-batch, .db-mig-state { grid-column: auto; grid-row: auto; }
        .db-mig-batch { text-align: left; }
        .db-mig-state { justify-self: start; }
    }
</style>
@endpush

@php
    $states = [
        'pending' => ['Pending', 'badge-in-progress'],
        'ran' => ['Ran', 'badge-done'],
        'missing' => ['Ran · file gone', 'badge-todo'],
    ];
@endphp

<section class="card db-list" aria-label="Migrations" data-db-filter-scope>
    @if ($error !== null)
        <div class="db-error">
            <h3>The migrations cannot be read</h3>
            <pre>{{ $error }}</pre>
        </div>
    @elseif ($migrations === [])
        <div class="empty">
            <p>The application has no migration yet. <code>php artisan make:migration</code> writes one.</p>
        </div>
    @else
        <div class="db-mig-top">
            <div class="db-mig-counts">
                <span class="badge badge-in-progress">{{ number_format($pending) }} pending</span>
                <span class="badge badge-done">{{ number_format($ran) }} ran</span>
                @if ($missing > 0)
                    <span class="badge badge-todo" title="In the {{ $table }} table, with no file left to run it again.">{{ number_format($missing) }} with the file gone</span>
                @endif
            </div>
            @if (! $installed)
                <p class="hint">The <code>{{ $table }}</code> table is not there yet: nothing ran on this connection. <code>php artisan migrate</code> creates it.</p>
            @elseif ($pending > 0)
                <p class="hint"><code>php artisan migrate</code> runs {{ $pending === 1 ? 'the one' : 'the '.number_format($pending) }} still to run, as batch {{ ($batch ?? 0) + 1 }}.</p>
            @else
                <p class="hint">Nothing to run.@if ($batch !== null) The last batch is {{ $batch }}.@endif</p>
            @endif
            @if (count($migrations) > 8)
                <label class="field db-filter" data-db-filter-wrap hidden>
                    <input type="search" placeholder="Find a migration…" autocomplete="off" data-db-filter aria-label="Find a migration">
                </label>
            @endif
        </div>
        <div class="db-mig-head" aria-hidden="true">
            <span>Migration</span>
            <span>Written</span>
            <span>Batch</span>
            <span>State</span>
        </div>
        @foreach ($migrations as $migration)
            <div class="db-mig-row @if ($migration['state'] === 'pending') is-pending @endif" data-db-item data-name="{{ strtolower($migration['name'].' '.$states[$migration['state']][0].' '.$migration['package']) }}">
                <div class="db-mig-name">
                    <strong>{{ $migration['title'] }}</strong>
                    <code title="{{ $migration['path'] ?? 'No file by this name is left.' }}">{{ $migration['name'] }}</code>
                    @if ($migration['package'] !== null)
                        <span class="db-tag">{{ $migration['package'] }}</span>
                    @endif
                </div>
                <span class="db-mig-date">{{ $migration['date'] ?? '—' }}</span>
                <span class="db-mig-batch">{{ $migration['batch'] === null ? '—' : 'Batch '.$migration['batch'] }}</span>
                <span class="db-mig-state"><span class="badge {{ $states[$migration['state']][1] }}">{{ $states[$migration['state']][0] }}</span></span>
            </div>
        @endforeach
        <p class="db-list-empty" data-db-filter-empty hidden>No migration matches.</p>
        <div class="db-list-foot">
            {{ number_format(count($migrations)) }} {{ count($migrations) === 1 ? 'migration' : 'migrations' }} · read from <code>{{ $table }}</code> and the migration folders, as <code>php artisan migrate:status</code> does. Nothing is run from here.
        </div>
    @endif
</section>
