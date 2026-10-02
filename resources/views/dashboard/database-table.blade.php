@extends('larapilot::dashboard.layout')

@section('title', $object['name'].' · Database')

@push('styles')
@include('larapilot::dashboard.partials.database-styles')
<style>
    .crumbs {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 2px 4px;
        margin: 0 0 14px;
        padding: 0;
        list-style: none;
        color: var(--muted);
        font-size: 0.84rem;
    }

    .crumbs li { display: inline-flex; align-items: center; gap: 4px; min-width: 0; }
    .crumbs li + li::before { content: '/'; color: var(--border-strong); }
    .crumbs a { color: var(--muted); }
    .crumbs a:hover { color: var(--accent); }
    .crumbs [aria-current] { color: var(--text); font-weight: 600; overflow-wrap: anywhere; }

    .db-head h2 { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; overflow-wrap: anywhere; }

    .db-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 18px;
        margin: 8px 0 0;
        padding: 0;
        list-style: none;
        color: var(--muted);
        font-size: 0.82rem;
        font-variant-numeric: tabular-nums;
    }

    .db-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 16px;
        align-items: start;
    }

    @media (min-width: 1000px) {
        .db-layout { grid-template-columns: 248px minmax(0, 1fr); gap: 20px; }
    }

    /* ---- tables beside the grid ---- */
    .db-side { padding: 0; }

    .db-side > summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 16px;
        color: var(--muted);
        font-size: 0.7rem;
        font-weight: 650;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        cursor: pointer;
        list-style: none;
        user-select: none;
    }

    .db-side > summary::-webkit-details-marker { display: none; }
    .db-side > summary > .icon { width: 15px; height: 15px; transition: transform 0.15s ease; }
    .db-side[open] > summary > .icon { transform: rotate(90deg); }

    .db-side-body { padding: 0 8px 12px; }
    .db-side-body .field { margin: 0 4px 8px; }

    @media (min-width: 1000px) {
        .db-side {
            position: sticky;
            top: 24px;
            max-height: calc(100vh - 48px);
            overflow-y: auto;
            scrollbar-width: thin;
        }

        .db-side > summary { cursor: default; pointer-events: none; }
        .db-side > summary > .icon { display: none; }
    }

    .db-side-list { margin: 0; padding: 0; list-style: none; }

    .db-side-list a {
        display: flex;
        align-items: center;
        gap: 8px;
        min-height: 34px;
        padding: 0 8px;
        border-radius: var(--radius-xs);
        color: var(--text-2);
        font-size: 0.86rem;
        text-decoration: none;
    }

    .db-side-list a:hover { background: var(--surface-3); text-decoration: none; }
    .db-side-list a .icon { width: 15px; height: 15px; color: var(--muted); }
    .db-side-list a span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .db-side-list a[aria-current] { background: var(--accent-soft); color: var(--accent-strong); font-weight: 600; }
    .db-side-list a[aria-current] .icon { color: var(--accent); }
    .db-side-note { margin: 8px; color: var(--muted); font-size: 0.8rem; }

    /* ---- rows / structure ---- */
    .db-tools {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 14px;
    }

    .db-search { display: flex; flex: 1 1 280px; gap: 8px; min-width: 0; }
    .db-search input { flex: 1; min-width: 0; }

    .db-filtered {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        max-width: 100%;
        padding: 4px 6px 4px 12px;
        border: 1px solid color-mix(in srgb, var(--accent) 45%, var(--border));
        border-radius: 999px;
        background: var(--accent-soft);
        color: var(--accent-strong);
        font-size: 0.8rem;
    }

    .db-filtered code { padding: 0; background: transparent; color: inherit; overflow-wrap: anywhere; }

    .db-filtered a {
        display: grid;
        place-items: center;
        width: 24px;
        height: 24px;
        border-radius: 999px;
        color: inherit;
    }

    .db-filtered a:hover { background: color-mix(in srgb, var(--accent) 18%, transparent); }
    .db-filtered a .icon { width: 14px; height: 14px; }

    .db-grid-card { overflow: hidden; }

    .db-grid-wrap {
        max-height: 70vh;
        overflow: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }

    .db-grid {
        width: max-content;
        min-width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.82rem;
    }

    .db-grid th,
    .db-grid td {
        max-width: 320px;
        padding: 7px 12px;
        border-bottom: 1px solid var(--border);
        text-align: left;
        vertical-align: top;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .db-grid thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: var(--surface-2);
        color: var(--text-2);
        font-weight: 600;
    }

    .db-grid thead th a { display: flex; align-items: center; gap: 5px; color: inherit; text-decoration: none; }
    .db-grid thead th a:hover { color: var(--accent); }
    .db-grid thead th .icon { width: 13px; height: 13px; color: var(--muted); }
    .db-grid thead th .is-up { transform: rotate(-90deg); color: var(--accent); }
    .db-grid thead th .is-down { transform: rotate(90deg); color: var(--accent); }
    .db-grid thead th small { display: block; color: var(--muted); font-family: var(--mono); font-size: 0.7rem; font-weight: 400; }

    .db-grid tbody tr { cursor: pointer; }
    .db-grid tbody tr:hover td { background: var(--surface-2); }
    .db-grid tbody tr:last-child td { border-bottom: 0; }

    .db-grid td { font-family: var(--mono); font-size: 0.78rem; color: var(--text); }
    .db-grid td.is-number { text-align: right; font-variant-numeric: tabular-nums; }
    .db-grid td.is-null { color: var(--muted); font-style: italic; }
    .db-grid thead th.is-number a { justify-content: flex-end; }
    .db-grid thead th.is-number { text-align: right; }
    .db-grid td.is-masked,
    .db-grid td.is-binary { color: var(--muted); }
    .db-grid td.is-bool { color: var(--violet); }

    .db-grid .db-open { width: 1%; padding: 2px 4px 2px 8px; }

    .db-grid .db-open button {
        display: grid;
        place-items: center;
        width: 30px;
        height: 30px;
        padding: 0;
        border: 0;
        border-radius: var(--radius-xs);
        background: transparent;
        color: var(--muted);
        cursor: pointer;
    }

    .db-grid .db-open button:hover { background: var(--surface-3); color: var(--accent); }
    .db-grid .db-open .icon { width: 15px; height: 15px; }

    .db-pager {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px 16px;
        padding: 10px 16px;
        border-top: 1px solid var(--border);
        background: var(--surface-2);
        color: var(--muted);
        font-size: 0.8rem;
        font-variant-numeric: tabular-nums;
    }

    .db-pager nav { display: flex; align-items: center; gap: 6px; }
    .db-pager .btn .icon { width: 15px; height: 15px; }

    /* ---- structure ---- */
    .db-structure { display: grid; gap: 16px; }
    .db-structure h3 { margin: 0; padding: 14px 16px; border-bottom: 1px solid var(--border); font-size: 0.95rem; }
    .db-structure .card { overflow: hidden; }

    .db-def {
        width: 100%;
        min-width: 560px;
        border-collapse: collapse;
        font-size: 0.84rem;
    }

    .db-def th,
    .db-def td {
        padding: 9px 16px;
        border-bottom: 1px solid var(--border);
        text-align: left;
        vertical-align: top;
    }

    .db-def tr:last-child td { border-bottom: 0; }

    .db-def th {
        color: var(--muted);
        font-size: 0.7rem;
        font-weight: 650;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .db-def td code { white-space: nowrap; }
    .db-def .db-col { display: flex; align-items: center; gap: 6px; font-weight: 600; white-space: nowrap; }
    .db-def .db-col .icon { width: 14px; height: 14px; color: var(--warn); }
    .db-def .db-notes { display: flex; flex-wrap: wrap; gap: 4px 6px; color: var(--muted); }
    .db-def .db-notes small { display: block; flex-basis: 100%; }
    .db-def-empty { margin: 0; padding: 18px 16px; color: var(--muted); font-size: 0.86rem; }

    .db-create-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding-right: 12px;
        border-bottom: 1px solid var(--border);
    }

    .db-structure .db-create-head h3 { border-bottom: 0; }

    .db-sql {
        margin: 0;
        padding: 14px 16px;
        max-height: 60vh;
        overflow: auto;
        background: var(--surface-2);
        font-family: var(--mono);
        font-size: 0.78rem;
        line-height: 1.6;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
        scrollbar-width: thin;
    }

    .db-sql code { padding: 0; background: transparent; color: var(--text); font: inherit; white-space: inherit; }

    /* ---- one row ---- */
    .db-modal {
        width: min(calc(100vw - 32px), 760px);
        max-height: min(86vh, 900px);
        padding: 0;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        background: var(--surface);
        color: var(--text);
        box-shadow: var(--shadow-lg);
    }

    .db-modal[open] { display: flex; flex-direction: column; }
    .db-modal::backdrop { background: rgba(10, 16, 22, 0.5); }

    .db-modal header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px 12px;
        padding: 16px 18px;
        border-bottom: 1px solid var(--border);
    }

    .db-modal h3 { margin: 0; font-size: 1.05rem; }
    .db-modal-actions { display: flex; flex-wrap: wrap; gap: 8px; }
    .db-modal-body { margin: 0; padding: 6px 18px 18px; overflow-y: auto; }

    .db-modal dt {
        display: flex;
        align-items: baseline;
        gap: 8px;
        margin-top: 12px;
        color: var(--text-2);
        font-size: 0.8rem;
        font-weight: 600;
    }

    .db-modal dt small { color: var(--muted); font-family: var(--mono); font-size: 0.72rem; font-weight: 400; }

    .db-modal dd {
        margin: 4px 0 0;
        padding: 8px 10px;
        border: 1px solid var(--border);
        border-radius: var(--radius-xs);
        background: var(--surface-2);
        font-family: var(--mono);
        font-size: 0.8rem;
        line-height: 1.55;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }

    .db-modal dd.is-null,
    .db-modal dd.is-masked { color: var(--muted); font-style: italic; }
</style>
@endpush

@section('content')
    @php
        $key = $object['key'];
        $isView = $object['kind'] === 'view';
        $state = array_filter([
            'q' => $search !== '' ? $search : null,
            'sort' => $sort,
            'dir' => $sort !== null && $direction === 'desc' ? 'desc' : null,
            'where' => $where,
            'is' => $where !== null ? $is : null,
        ], static fn ($value): bool => $value !== null);
        $link = static fn (array $changes = []): string => route('larapilot.dashboard.database.table', array_filter(
            array_merge(['table' => $key], $state, $changes),
            static fn ($value): bool => $value !== null
        ));
        $types = [];

        foreach ($columns as $column) {
            $types[$column['name']] = $column['type'];
        }
    @endphp

    <nav aria-label="Breadcrumb">
        <ol class="crumbs">
            <li><a href="{{ route('larapilot.dashboard.database') }}">Database</a></li>
            <li><span aria-current="page">{{ $key }}</span></li>
        </ol>
    </nav>

    <header class="page-head db-head">
        <div>
            <h2>{{ $object['name'] }}@if ($isView) <span class="db-tag">View</span>@endif</h2>
            @if ($object['comment'] !== null)
                <p class="sub">{{ $object['comment'] }}</p>
            @endif
            <ul class="db-meta">
                @if ($error === null)
                    <li>{{ number_format($total) }} {{ $total === 1 ? 'row' : 'rows' }}{{ $search !== '' || $where !== null ? ' found' : '' }}</li>
                @endif
                <li>{{ count($columns) }} {{ count($columns) === 1 ? 'column' : 'columns' }}</li>
                @if ($object['size_label'] !== null)
                    <li>{{ $object['size_label'] }}</li>
                @endif
                @if ($object['schema'] !== null && $schemas)
                    <li>Schema {{ $object['schema'] }}</li>
                @endif
            </ul>
        </div>
    </header>

    <div class="db-layout">
        <details class="card db-side" open data-db-side data-db-filter-scope>
            <summary>Tables @include('larapilot::dashboard.partials.icon', ['name' => 'chevron'])</summary>
            <div class="db-side-body">
                @if (count($objects) > 8)
                    <label class="field" data-db-filter-wrap hidden>
                        <input type="search" placeholder="Find a table…" autocomplete="off" data-db-filter aria-label="Find a table">
                    </label>
                @endif
                <ul class="db-side-list">
                    @foreach ($objects as $item)
                        <li data-db-item data-name="{{ strtolower($item['key']) }}">
                            <a href="{{ route('larapilot.dashboard.database.table', ['table' => $item['key']]) }}" @if ($item['key'] === $key) aria-current="page" @endif title="{{ $item['key'] }}">
                                @include('larapilot::dashboard.partials.icon', ['name' => $item['kind'] === 'view' ? 'view' : 'table'])
                                <span>@if ($schemas && $item['schema'] !== null)<span class="db-schema">{{ $item['schema'] }}.</span>@endif{{ $item['name'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <p class="db-side-note" data-db-filter-empty hidden>No table matches.</p>
            </div>
        </details>

        <div>
            <nav class="db-tabs" aria-label="Table">
                <a href="{{ $link() }}" @if ($tab === 'rows') aria-current="page" @endif>@include('larapilot::dashboard.partials.icon', ['name' => 'table'])Rows</a>
                <a href="{{ $link(['tab' => 'structure']) }}" @if ($tab === 'structure') aria-current="page" @endif>@include('larapilot::dashboard.partials.icon', ['name' => 'key'])Structure</a>
            </nav>

            @if ($tab === 'structure')
                <div class="db-structure">
                    <section class="card" aria-labelledby="db-columns-title">
                        <h3 id="db-columns-title">Columns</h3>
                        @if ($columns === [])
                            <p class="db-def-empty">The driver did not describe the columns.</p>
                        @else
                            <div class="table-wrap">
                                <table class="db-def">
                                    <thead>
                                        <tr><th>Column</th><th>Type</th><th>Null</th><th>Default</th><th>Notes</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($columns as $column)
                                            <tr>
                                                <td><span class="db-col">@if ($column['primary'])@include('larapilot::dashboard.partials.icon', ['name' => 'key'])@endif{{ $column['name'] }}</span></td>
                                                <td><code>{{ $column['type'] }}</code></td>
                                                <td>{{ $column['nullable'] ? 'Yes' : 'No' }}</td>
                                                <td>@if ($column['default'] !== null)<code>{{ $column['default'] }}</code>@else<span class="hint">—</span>@endif</td>
                                                <td>
                                                    <span class="db-notes">
                                                        @if ($column['primary'])<span class="db-tag">Primary</span>@endif
                                                        @if ($column['auto_increment'])<span class="db-tag">Auto increment</span>@endif
                                                        @if ($column['masked'])<span class="db-tag" title="Shown as asterisks, never searched or sorted.">Values hidden</span>@endif
                                                        @if ($column['reference'] !== null)
                                                            <a href="{{ route('larapilot.dashboard.database.table', ['table' => $column['reference']['key']]) }}">→ {{ $column['reference']['key'] }}.{{ $column['reference']['column'] }}</a>
                                                        @endif
                                                        @if ($column['comment'] !== null)<small>{{ $column['comment'] }}</small>@endif
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </section>

                    @unless ($isView)
                        <section class="card" aria-labelledby="db-indexes-title">
                            <h3 id="db-indexes-title">Indexes</h3>
                            @if ($indexes === [])
                                <p class="db-def-empty">No index.</p>
                            @else
                                <div class="table-wrap">
                                    <table class="db-def">
                                        <thead>
                                            <tr><th>Name</th><th>Columns</th><th>Kind</th></tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($indexes as $index)
                                                <tr>
                                                    <td><code>{{ $index['name'] }}</code></td>
                                                    <td>{{ implode(', ', $index['columns']) }}</td>
                                                    <td>{{ $index['primary'] ? 'Primary key' : ($index['unique'] ? 'Unique' : 'Index') }}@if ($index['type'] !== null && ! in_array(strtolower($index['type']), ['btree', 'primary', 'unique', 'index'], true)) · {{ $index['type'] }}@endif</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </section>

                        <section class="card" aria-labelledby="db-foreign-title">
                            <h3 id="db-foreign-title">Foreign keys</h3>
                            @if ($foreign_keys === [])
                                <p class="db-def-empty">No foreign key.</p>
                            @else
                                <div class="table-wrap">
                                    <table class="db-def">
                                        <thead>
                                            <tr><th>Columns</th><th>References</th><th>On update</th><th>On delete</th></tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($foreign_keys as $foreign)
                                                <tr>
                                                    <td>{{ implode(', ', $foreign['columns']) }}</td>
                                                    <td>
                                                        @if ($foreign['key'] !== null)
                                                            <a href="{{ route('larapilot.dashboard.database.table', ['table' => $foreign['key']]) }}">{{ $foreign['key'] }}</a>
                                                        @else
                                                            {{ $foreign['table'] }}
                                                        @endif
                                                        ({{ implode(', ', $foreign['foreign_columns']) }})
                                                    </td>
                                                    <td>{{ $foreign['on_update'] ?? '—' }}</td>
                                                    <td>{{ $foreign['on_delete'] ?? '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </section>
                    @endunless

                    <section class="card" aria-labelledby="db-create-title">
                        <div class="db-create-head">
                            <h3 id="db-create-title">Create statement</h3>
                            @if ($definition !== [])
                                <button type="button" class="btn ghost small" data-db-copy-text="db-create-sql" data-label="Copy SQL">Copy SQL</button>
                            @endif
                        </div>
                        @if ($definition === [])
                            <p class="db-def-empty">The driver did not give the statement that creates this {{ $isView ? 'view' : 'table' }}.</p>
                        @else
                            <pre class="db-sql" id="db-create-sql" tabindex="0"><code>{{ implode(";\n\n", $definition) }};</code></pre>
                        @endif
                    </section>
                </div>
            @else
                @if ($searchable || $where !== null)
                <div class="db-tools">
                    @if ($searchable)
                        <form class="db-search" method="get" action="{{ route('larapilot.dashboard.database.table', ['table' => $key]) }}" role="search">
                            @foreach (['sort' => $sort, 'dir' => $state['dir'] ?? null, 'where' => $where, 'is' => $where !== null ? $is : null] as $name => $value)
                                @if ($value !== null)
                                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                                @endif
                            @endforeach
                            <input class="control" type="search" name="q" value="{{ $search }}" placeholder="Search the text columns…" aria-label="Search the rows">
                            <button type="submit" class="btn ghost">@include('larapilot::dashboard.partials.icon', ['name' => 'search'])Search</button>
                        </form>
                    @endif
                    @if ($search !== '' && $searchable)
                        <span class="db-filtered">Text has <code>{{ $search }}</code><a href="{{ $link(['q' => null, 'page' => null]) }}" aria-label="Clear the search">@include('larapilot::dashboard.partials.icon', ['name' => 'close'])</a></span>
                    @endif
                    @if ($where !== null)
                        <span class="db-filtered"><code>{{ $where }} = {{ $is }}</code><a href="{{ $link(['where' => null, 'is' => null, 'page' => null]) }}" aria-label="Remove the filter">@include('larapilot::dashboard.partials.icon', ['name' => 'close'])</a></span>
                    @endif
                </div>
                @endif

                @if ($error !== null)
                    @include('larapilot::dashboard.partials.database-error', ['error' => $error, 'connection' => $connection])
                @else
                    <section class="card db-grid-card" aria-label="Rows of {{ $object['name'] }}">
                        @if ($rows === [])
                            <div class="empty">
                                <p>{{ $search !== '' || $where !== null ? 'No row matches.' : 'This '.($isView ? 'view' : 'table').' is empty.' }}</p>
                            </div>
                        @else
                            <div class="db-grid-wrap">
                                <table class="db-grid">
                                    <thead>
                                        <tr>
                                            <th class="db-open"><span hidden>Open</span></th>
                                            @foreach ($rows[0] as $cell)
                                                @php
                                                    $meta = null;

                                                    foreach ($columns as $column) {
                                                        if ($column['name'] === $cell['column']) {
                                                            $meta = $column;
                                                            break;
                                                        }
                                                    }

                                                    $active = $sort === $cell['column'];
                                                    $next = $active && $direction === 'asc' ? 'desc' : null;
                                                @endphp
                                                <th scope="col" @class(['is-number' => $meta !== null && $meta['numeric']]) @if ($active) aria-sort="{{ $direction === 'desc' ? 'descending' : 'ascending' }}" @endif>
                                                    @if ($meta !== null && ! $meta['masked'])
                                                        <a href="{{ $link(['sort' => $cell['column'], 'dir' => $next, 'page' => null]) }}" title="Sort by {{ $cell['column'] }}">
                                                            @if ($meta['primary'])@include('larapilot::dashboard.partials.icon', ['name' => 'key'])@endif
                                                            {{ $cell['column'] }}
                                                            @if ($active)@include('larapilot::dashboard.partials.icon', ['name' => 'chevron', 'class' => $direction === 'desc' ? 'is-down' : 'is-up'])@endif
                                                        </a>
                                                    @else
                                                        <span title="{{ $meta !== null ? 'Values hidden' : '' }}">{{ $cell['column'] }}</span>
                                                    @endif
                                                    @if ($meta !== null)
                                                        <small>{{ $meta['type'] }}</small>
                                                    @endif
                                                </th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($rows as $index => $row)
                                            <tr data-db-row="{{ $index }}">
                                                <td class="db-open"><button type="button" data-db-row-open="{{ $index }}" aria-label="Open row {{ $from + $index }}" title="Open the row">@include('larapilot::dashboard.partials.icon', ['name' => 'expand'])</button></td>
                                                @foreach ($row as $cell)
                                                    <td class="is-{{ $cell['kind'] }}">
                                                        @if ($cell['link'] !== null)
                                                            <a href="{{ route('larapilot.dashboard.database.table', ['table' => $cell['link']['key'], 'where' => $cell['link']['column'], 'is' => $cell['link']['value']]) }}" title="Open {{ $cell['link']['key'] }} where {{ $cell['link']['column'] }} = {{ $cell['link']['value'] }}">{{ $cell['text'] }}</a>
                                                        @else
                                                            {{ $cell['text'] }}
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        <div class="db-pager">
                            <span>
                                @if ($total > 0)
                                    Rows {{ number_format($from) }}–{{ number_format($to) }} of {{ number_format($total) }}
                                @else
                                    0 rows
                                @endif
                            </span>
                            @if ($last_page > 1)
                                <nav aria-label="Pages">
                                    <a @class(['btn', 'ghost', 'small', 'is-disabled' => $page <= 1]) href="{{ $link(['page' => 1]) }}" aria-label="First page">«</a>
                                    <a @class(['btn', 'ghost', 'small', 'is-disabled' => $page <= 1]) href="{{ $link(['page' => max(1, $page - 1)]) }}" aria-label="Previous page">@include('larapilot::dashboard.partials.icon', ['name' => 'chevron-left'])</a>
                                    <span>Page {{ number_format($page) }} of {{ number_format($last_page) }}</span>
                                    <a @class(['btn', 'ghost', 'small', 'is-disabled' => $page >= $last_page]) href="{{ $link(['page' => min($last_page, $page + 1)]) }}" aria-label="Next page">@include('larapilot::dashboard.partials.icon', ['name' => 'chevron'])</a>
                                    <a @class(['btn', 'ghost', 'small', 'is-disabled' => $page >= $last_page]) href="{{ $link(['page' => $last_page]) }}" aria-label="Last page">»</a>
                                </nav>
                            @endif
                        </div>
                    </section>

                    <dialog class="db-modal" id="db-row-dialog" aria-labelledby="db-row-title">
                        <header>
                            <h3 id="db-row-title">Row</h3>
                            <div class="db-modal-actions">
                                <button type="button" class="btn ghost small" data-db-copy>Copy as JSON</button>
                                @if ($inserts !== [])
                                    <button type="button" class="btn ghost small" data-db-insert>Copy as SQL INSERT</button>
                                @endif
                                <button type="button" class="btn small" data-db-close>Close</button>
                            </div>
                        </header>
                        <dl class="db-modal-body" data-db-fields></dl>
                    </dialog>

                    <script type="application/json" id="db-rows">{!! json_encode([
                        'from' => $from,
                        'types' => $types,
                        // One statement for each row, what the page hides left out; null for a row too large to copy.
                        'sql' => $inserts,
                        'rows' => array_map(static fn (array $row): array => array_map(
                            static fn (array $cell): array => [$cell['column'], $cell['kind'], $cell['full']],
                            $row
                        ), $rows),
                    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) !!}</script>
                @endif
            @endif
        </div>
    </div>
@endsection

@push('scripts')
@include('larapilot::dashboard.partials.database-filter-script')
<script>
    (() => {
        // On a phone the list of tables starts folded, so the rows come first.
        document.querySelectorAll('[data-db-side]').forEach((side) => {
            const wide = window.matchMedia('(min-width: 1000px)');
            const sync = () => {
                side.open = wide.matches;
            };

            sync();
            wide.addEventListener?.('change', sync);
        });

        const copyText = async (button, text, label) => {
            try {
                await navigator.clipboard.writeText(text);
                button.textContent = 'Copied';
            } catch (error) {
                button.textContent = 'Copy failed';
            }

            setTimeout(() => {
                button.textContent = label;
            }, 1600);
        };

        document.querySelectorAll('[data-db-copy-text]').forEach((button) => {
            button.addEventListener('click', () => {
                const target = document.getElementById(button.dataset.dbCopyText);

                if (target) {
                    copyText(button, target.textContent, button.dataset.label || 'Copy');
                }
            });
        });

        const source = document.getElementById('db-rows');
        const dialog = document.getElementById('db-row-dialog');

        if (!source || !dialog || typeof dialog.showModal !== 'function') {
            return;
        }

        const data = JSON.parse(source.textContent || '{}');
        const fields = dialog.querySelector('[data-db-fields]');
        const title = dialog.querySelector('#db-row-title');
        const insert = dialog.querySelector('[data-db-insert]');
        let current = null;
        let statement = null;

        const open = (index) => {
            const row = (data.rows || [])[index];

            if (!row) {
                return;
            }

            current = row;
            statement = (data.sql || [])[index] || null;

            if (insert) {
                insert.disabled = !statement;
                insert.title = statement ? '' : 'This row is too large to copy as one statement: the SQL download holds it.';
            }

            title.textContent = 'Row ' + ((data.from || 1) + index).toLocaleString();
            fields.textContent = '';

            row.forEach(([column, kind, value]) => {
                const term = document.createElement('dt');
                const detail = document.createElement('dd');
                const type = (data.types || {})[column];

                term.append(column);

                if (type) {
                    const small = document.createElement('small');
                    small.textContent = type;
                    term.append(small);
                }

                detail.className = 'is-' + kind;
                detail.textContent = value;
                fields.append(term, detail);
            });

            dialog.showModal();
            fields.scrollTop = 0;
        };

        document.querySelectorAll('[data-db-row]').forEach((tr) => {
            tr.addEventListener('click', (event) => {
                // A link in a cell keeps its own job.
                if (event.target.closest('a')) {
                    return;
                }

                open(Number(tr.dataset.dbRow));
            });
        });

        dialog.querySelector('[data-db-close]').addEventListener('click', () => dialog.close());

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });

        const copy = dialog.querySelector('[data-db-copy]');

        copy.addEventListener('click', () => {
            if (!current) {
                return;
            }

            const record = {};

            current.forEach(([column, kind, value]) => {
                if (kind === 'null') {
                    record[column] = null;
                } else if (kind === 'bool') {
                    record[column] = value === 'true';
                } else if (kind === 'number' && String(Number(value)) === value && Number.isSafeInteger(Math.trunc(Number(value)))) {
                    record[column] = Number(value);
                } else {
                    record[column] = value;
                }
            });

            copyText(copy, JSON.stringify(record, null, 2), 'Copy as JSON');
        });

        insert?.addEventListener('click', () => {
            if (statement) {
                copyText(insert, statement, 'Copy as SQL INSERT');
            }
        });
    })();
</script>
@endpush
