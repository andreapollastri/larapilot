@extends('larapilot::dashboard.layout')

@section('title', 'PRD')

@push('styles')
<style>
    .prd-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 16px;
        align-items: start;
    }

    @media (min-width: 1000px) {
        .prd-layout { grid-template-columns: 240px minmax(0, 1fr); gap: 22px; }
    }

    .toc { padding: 0; }

    .toc > summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
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

    .toc > summary::-webkit-details-marker { display: none; }
    .toc > summary .icon { width: 15px; height: 15px; transition: transform 0.15s ease; }
    .toc[open] > summary .icon { transform: rotate(90deg); }

    .toc ul {
        list-style: none;
        margin: 0;
        padding: 0 8px 12px;
    }

    .toc a {
        display: block;
        padding: 6px 9px;
        border-radius: var(--radius-xs);
        color: var(--text-2);
        font-size: 0.86rem;
        line-height: 1.35;
        text-decoration: none;
    }

    .toc a:hover { background: var(--surface-3); color: var(--text); }
    .toc .level-3 a { padding-left: 22px; color: var(--muted); font-size: 0.82rem; }

    @media (min-width: 1000px) {
        .toc {
            position: sticky;
            top: 24px;
            max-height: calc(100vh - 48px);
            overflow-y: auto;
            scrollbar-width: thin;
        }

        .toc > summary { cursor: default; pointer-events: none; }
        .toc > summary .icon { display: none; }
    }

    .prd-content { padding: 22px 18px; }

    @media (min-width: 640px) {
        .prd-content { padding: 30px 34px; }
    }
</style>
@endpush

@section('content')
    <header class="page-head">
        <div>
            <h2>PRD</h2>
            <p class="sub">The product requirements document, as the skills read it from <code>.larapilot/docs/PRD.md</code>.</p>
        </div>
        @if ($prd !== null)
            <div class="page-actions">
                <a class="btn ghost" href="{{ route('larapilot.dashboard.prd.summary') }}">@include('larapilot::dashboard.partials.icon', ['name' => 'download']){{ $summaryLabel }}</a>
            </div>
        @endif
    </header>

    @if ($prd === null)
        <div class="card empty">
            <p>No PRD found. Run <code>/larapilot-inception</code> to create <code>.larapilot/docs/PRD.md</code>.</p>
        </div>
    @else
        <div class="prd-layout">
            <details class="card toc" open data-toc>
                <summary>Sections @include('larapilot::dashboard.partials.icon', ['name' => 'chevron'])</summary>
                <ul>
                    @foreach ($prd['headings'] as $heading)
                        <li @class(['level-3' => $heading['level'] === 3])>
                            <a href="#{{ $heading['id'] }}">{{ $heading['title'] }}</a>
                        </li>
                    @endforeach
                    @if (($decisions['entry_count'] ?? 0) > 0)
                        <li>
                            <a href="#decision-journal">Decision journal</a>
                        </li>
                    @endif
                </ul>
            </details>

            <article class="card prd-content markdown">
                {!! $prd['html'] !!}
            </article>
        </div>
    @endif

    @include('larapilot::dashboard.partials.decisions', ['decisions' => $decisions ?? null])

    @push('scripts')
    <script>
        // On a phone the section list starts folded, so the document comes first.
        document.querySelectorAll('[data-toc]').forEach(function (toc) {
            var wide = window.matchMedia('(min-width: 1000px)');
            var sync = function () {
                toc.open = wide.matches;
            };

            sync();

            if (wide.addEventListener) {
                wide.addEventListener('change', sync);
            }

            toc.querySelectorAll('a').forEach(function (link) {
                link.addEventListener('click', function () {
                    if (! wide.matches) {
                        toc.open = false;
                    }
                });
            });
        });

        document.querySelectorAll('.decisions-timeline[data-exclusive-accordion]').forEach(function (accordion) {
            accordion.querySelectorAll('.decision-entry').forEach(function (item) {
                item.addEventListener('toggle', function () {
                    if (! item.open) {
                        return;
                    }

                    accordion.querySelectorAll('.decision-entry').forEach(function (other) {
                        if (other !== item) {
                            other.open = false;
                        }
                    });
                });
            });
        });
    </script>
    @endpush
@endsection
