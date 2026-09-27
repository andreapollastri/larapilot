@extends('larapilot::dashboard.layout')

@section('title', 'Inception')

@push('styles')
<style>
    .inception-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 1px;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        background: var(--border);
        overflow: hidden;
        box-shadow: var(--shadow);
    }

    @media (min-width: 640px) {
        .inception-grid { grid-template-columns: repeat(auto-fill, minmax(270px, 1fr)); }
    }

    .inception-item {
        display: flex;
        flex-direction: column;
        gap: 6px;
        padding: 16px 18px;
        background: var(--surface);
    }

    .inception-item .label {
        color: var(--muted);
        font-size: 0.7rem;
        font-weight: 650;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .inception-item .value {
        font-size: 0.95rem;
        font-weight: 550;
        line-height: 1.4;
        overflow-wrap: anywhere;
    }

    .path-note {
        margin: 16px 0 0;
        color: var(--muted);
        font-size: 0.8rem;
    }
</style>
@endpush

@section('content')
    <div class="inception-page">
        <header class="page-head">
            <div>
                <h2>Inception choices</h2>
                <p class="sub">Discovery decisions synced from the PRD into <code>.larapilot/choices.yaml</code>. Run <code>/larapilot-inception</code>, then refresh with <code>larapilot:choices-set --from-prd</code>.</p>
            </div>
        </header>

        @if (($inception ?? []) === [])
            <div class="card empty">
                <p>No choices yet. Run inception, then <code>larapilot:choices-set --from-prd</code>.</p>
            </div>
        @else
            <div class="inception-grid">
                @foreach ($inception as $label => $value)
                    <div class="inception-item">
                        <span class="label">{{ $label }}</span>
                        <span class="value">{{ is_array($value) ? json_encode($value) : $value }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <p class="path-note">
            Snapshot: <code>{{ $path ?? '.larapilot/choices.yaml' }}</code>
            @if (! empty($updated_at))
                · updated {{ $updated_at }}
            @endif
        </p>
    </div>
@endsection
