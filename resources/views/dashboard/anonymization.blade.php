@extends('larapilot::dashboard.layout')

@section('title', 'Anonymization')

@push('styles')
<style>
    .anon-top { display: grid; gap: 16px; grid-template-columns: minmax(0, 1fr); margin-bottom: 18px; }
    @media (min-width: 1000px) { .anon-top { grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); } }
    .anon-card { padding: 18px 20px; }
    .anon-card h3 { margin: 0 0 6px; font-size: 1rem; }
    .anon-card p { margin: 0 0 8px; color: var(--text-2); font-size: 0.88rem; }
    .anon-card ul { margin: 6px 0 0; padding-left: 1.1em; color: var(--text-2); font-size: 0.86rem; }
    .anon-card li { margin-bottom: 4px; }
    .anon-locale { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-end; margin-top: 10px; }
    .anon-locale .field { display: grid; gap: 4px; flex: 1 1 220px; min-width: 0; }
    .anon-locale .field > span { font-size: 0.78rem; font-weight: 600; color: var(--text-2); }
    .anon-locale select { width: 100%; }
    .anon-legend { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
    .anon-pill { display: inline-flex; align-items: center; gap: 5px; padding: 2px 9px; border-radius: 999px; font-size: 0.74rem; font-weight: 600; }
    .anon-pill.keep { background: var(--surface-3); color: var(--muted); }
    .anon-pill.mask { background: color-mix(in srgb, var(--danger-solid) 13%, transparent); color: var(--danger-solid); }
    .anon-pill.fake { background: color-mix(in srgb, var(--accent) 14%, transparent); color: var(--accent); }
    .anon-tools { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 0 0 12px; }
    .anon-tools input[type="search"] { flex: 1 1 240px; min-width: 0; max-width: 360px; }
    .anon-table { padding: 0; overflow: hidden; }
    .anon-table + .anon-table { margin-top: 10px; }
    .anon-table > summary { display: flex; flex-wrap: wrap; gap: 6px 12px; align-items: center; padding: 12px 16px; cursor: pointer; list-style: none; }
    .anon-table > summary::-webkit-details-marker { display: none; }
    .anon-table > summary .icon { width: 14px; height: 14px; color: var(--muted); transition: transform 0.15s ease; }
    .anon-table[open] > summary .icon { transform: rotate(90deg); }
    .anon-table > summary strong { overflow-wrap: anywhere; }
    .anon-table .count { color: var(--muted); font-size: 0.78rem; }
    .anon-scroll { overflow-x: auto; }
    .anon-grid { width: 100%; border-collapse: collapse; min-width: 640px; }
    .anon-grid th, .anon-grid td { padding: 8px 14px; border-top: 1px solid var(--border); text-align: left; vertical-align: middle; }
    .anon-grid th { color: var(--muted); font-size: 0.7rem; font-weight: 650; letter-spacing: 0.08em; text-transform: uppercase; }
    .anon-grid code { font-size: 0.84rem; }
    .anon-grid .type { color: var(--muted); font-size: 0.78rem; font-family: var(--mono); overflow-wrap: anywhere; }
    .anon-grid select { min-height: 34px; padding: 4px 28px 4px 10px; font-size: 0.85rem; width: 100%; max-width: 320px; }
    .anon-grid .sample { color: var(--text-2); font-size: 0.82rem; font-family: var(--mono); overflow-wrap: anywhere; max-width: 260px; }
    .anon-grid tr.is-custom td:first-child { box-shadow: inset 3px 0 0 var(--accent); }
    .anon-save { display: flex; flex-wrap: wrap; gap: 10px; justify-content: space-between; align-items: center; padding: 12px 16px; border-top: 1px solid var(--border); }
    .anon-secret { color: var(--muted); font-size: 0.84rem; }
</style>
@endpush

@section('content')
    <header class="page-head">
        <div>
            <h2>Anonymization</h2>
            <p class="sub">What the personal data of the database becomes before it leaves Larapilot anonymized — on the Database page for a developer in production, and in every SQL dump or seeder downloaded with <strong>Anonymize</strong>. The map is <code>{{ $path }}</code>: commit it, so every environment anonymizes the same way.</p>
        </div>
    </header>

    <div class="anon-top">
        <section class="card anon-card">
            <h3>How it works</h3>
            <ul>
                <li><strong>In production</strong> a developer always sees and downloads the anonymized rows, and cannot turn it off. An admin sees the real rows and ticks <strong>Anonymize</strong> on Database for an anonymized download.</li>
                <li>Every column without a line of its own follows a <strong>safe default</strong>: names, emails, phones, addresses, identifiers and free text are replaced; keys, record dates, statuses and enumerations are kept so the copy still imports.</li>
                <li>Passwords, tokens and secrets are <strong>always hidden</strong>. Migrations are structure only: they hold no row.</li>
                <li>The same value always becomes the same replacement on this install — seeded with an HMAC keyed with <code>APP_KEY</code> — so relations survive, and nothing of the original can be read back from the copy.</li>
            </ul>
            <div class="anon-legend" aria-label="Legend">
                <span class="anon-pill keep">Keep</span>
                <span class="anon-pill mask">Hide</span>
                <span class="anon-pill fake">Replaced with Faker</span>
            </div>
        </section>

        <section class="card anon-card">
            <h3>Language of the replacements</h3>
            <p>The <a href="https://fakerphp.org/locales/" target="_blank" rel="noopener noreferrer">Faker locale</a> names, addresses, phones and the identifiers of that country are written in. A locale adds formatters of its own — an Italian <em>codice fiscale</em>, a French <em>NIR</em>, a Spanish <em>DNI</em> — listed under “Only in …” below.</p>
            <form class="anon-locale" method="post" action="{{ route('larapilot.dashboard.anonymization.locale') }}">
                @csrf
                <label class="field">
                    <span>Faker locale</span>
                    <select class="control" name="locale">
                        @foreach ($locales as $code => $label)
                            <option value="{{ $code }}" @selected($code === $locale)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="btn ghost">Use this locale</button>
            </form>
            @if ($rules > 0)
                <p style="margin-top: 10px">{{ $rules }} {{ $rules === 1 ? 'column has' : 'columns have' }} a line of {{ $rules === 1 ? 'its' : 'their' }} own; everything else follows the default.</p>
            @endif
        </section>
    </div>

    @if ($broken)
        <div class="flash flash--error" role="alert"><strong><code>{{ $path }}</code> could not be read, so nothing in it applies — the defaults still do. Saving a table writes it again.</strong></div>
    @endif

    @if ($error !== null)
        <section class="card anon-card">
            <p>The database cannot be read, so there is no column to map. {{ $error }}</p>
        </section>
    @elseif ($tables === [])
        <section class="card anon-card">
            <p>This database has no table yet.</p>
        </section>
    @else
        <div class="anon-tools">
            <input class="control" type="search" placeholder="Find a table…" autocomplete="off" data-anon-filter aria-label="Find a table">
            <span class="hint">{{ count($tables) }} {{ count($tables) === 1 ? 'table' : 'tables' }} · the example column shows what a value becomes</span>
        </div>

        @foreach ($tables as $table)
            @php $custom = count(array_filter($table['columns'], static fn (array $column): bool => $column['saved'] !== '' && ! $column['secret'])); @endphp
            <details class="card anon-table" id="map-{{ $table['key'] }}" data-anon-table="{{ strtolower($table['name']) }}" @if ($loop->count <= 6 || $custom > 0) open @endif>
                <summary>
                    @include('larapilot::dashboard.partials.icon', ['name' => 'chevron'])
                    <strong>{{ $table['name'] }}</strong>
                    <span class="count">{{ count($table['columns']) }} {{ count($table['columns']) === 1 ? 'column' : 'columns' }}{{ $custom > 0 ? ' · '.$custom.' set by hand' : '' }}{{ $table['framework'] ? ' · Laravel table' : '' }}</span>
                </summary>
                <form method="post" action="{{ route('larapilot.dashboard.anonymization.update') }}">
                    @csrf
                    <input type="hidden" name="table" value="{{ $table['key'] }}">
                    <div class="anon-scroll">
                        <table class="anon-grid">
                            <thead>
                                <tr><th>Column</th><th>Type</th><th>Leaves as</th><th>Example</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($table['columns'] as $column)
                                    @php $effective = $column['saved'] !== '' ? $column['saved'] : $column['suggestion']; @endphp
                                    <tr @class(['is-custom' => $column['saved'] !== '' && ! $column['secret']])>
                                        <td><code>{{ $column['name'] }}</code></td>
                                        <td class="type">{{ $column['type'] }}</td>
                                        <td>
                                            @if ($column['secret'])
                                                <span class="anon-pill mask">Always hidden — a secret</span>
                                            @else
                                                <select class="control" name="columns[{{ $column['name'] }}]" aria-label="{{ $table['name'] }}.{{ $column['name'] }}">
                                                    <option value="" @selected($column['saved'] === '')>Default — {{ $labels[$column['suggestion']] ?? $column['suggestion'] }}</option>
                                                    @foreach ($groups as $group => $formatters)
                                                        @continue($column['kept_only'] && $group !== 'Strategy')
                                                        <optgroup label="{{ $group }}">
                                                            @foreach ($formatters as $key => $label)
                                                                @continue($column['kept_only'] && ! in_array($key, ['keep', 'mask'], true))
                                                                <option value="{{ $key }}" @selected($column['saved'] === $key)>{{ $label }}</option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endforeach
                                                </select>
                                            @endif
                                        </td>
                                        <td class="sample">
                                            @if ($effective === 'keep')
                                                <span class="anon-pill keep">Kept</span>
                                            @elseif ($effective === 'mask')
                                                <span class="anon-pill mask">Hidden</span>
                                            @else
                                                {{ \Illuminate\Support\Str::limit($column['sample'], 60) }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="anon-save">
                        <span class="hint">A choice equal to the default is not stored.</span>
                        <button type="submit" class="btn">Save {{ $table['name'] }}</button>
                    </div>
                </form>
            </details>
        @endforeach
    @endif
@endsection

@push('scripts')
<script>
    (function () {
        var input = document.querySelector('[data-anon-filter]');

        if (! input) {
            return;
        }

        input.addEventListener('input', function () {
            var term = input.value.trim().toLowerCase();

            document.querySelectorAll('[data-anon-table]').forEach(function (table) {
                table.hidden = term !== '' && table.getAttribute('data-anon-table').indexOf(term) === -1;
            });
        });
    })();
</script>
@endpush
