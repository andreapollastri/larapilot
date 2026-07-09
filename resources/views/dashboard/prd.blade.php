@extends('larapilot::dashboard.layout')

@section('title', 'PRD')

@push('styles')
<style>
    .prd-layout {
        display: grid;
        grid-template-columns: 240px 1fr;
        gap: 20px;
        align-items: start;
    }

    @media (max-width: 900px) {
        .prd-layout {
            grid-template-columns: 1fr;
        }
    }

    .toc {
        position: sticky;
        top: 20px;
        padding: 16px;
    }

    .toc h2 {
        margin: 0 0 12px;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--muted);
    }

    .toc ul {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .toc li {
        margin: 6px 0;
    }

    .toc a {
        font-size: 0.875rem;
        color: var(--text);
    }

    .toc .level-3 {
        padding-left: 12px;
    }

    .prd-content {
        padding: 24px 28px;
    }
</style>
@endpush

@section('content')
    @if ($prd === null)
        <div class="card empty">
            <p>No PRD found. Run <code>/larapilot-inception</code> to create <code>.larapilot/docs/PRD.md</code>.</p>
        </div>
    @else
        <div class="prd-layout">
            <aside class="card toc">
                <h2>Sections</h2>
                <ul>
                    @foreach ($prd['headings'] as $heading)
                        <li @class(['level-3' => $heading['level'] === 3])>
                            <a href="#{{ $heading['id'] }}">{{ $heading['title'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </aside>

            <article class="card prd-content markdown">
                {!! $prd['html'] !!}
            </article>
        </div>
    @endif
@endsection
