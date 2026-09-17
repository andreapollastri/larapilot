@extends('larapilot::dashboard.layout')

@section('title', 'Inception')

@push('styles')
<style>
    body .shell:has(.inception-page) {
        max-width: none;
        padding-left: max(20px, 4vw);
        padding-right: max(20px, 4vw);
    }

    .inception-page .panel {
        padding: 24px 28px;
    }

    .inception-page h2 {
        margin: 0 0 6px;
        font-size: 1.15rem;
    }

    .inception-page .sub {
        margin: 0 0 24px;
        color: var(--muted);
        font-size: 0.9rem;
        max-width: 72ch;
    }

    .inception-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 12px;
    }

    .inception-item {
        padding: 14px 16px;
        border-radius: 10px;
        border: 1px solid var(--border);
        background: color-mix(in srgb, var(--surface) 92%, var(--bg));
    }

    .inception-item .label {
        display: block;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--muted);
        margin-bottom: 6px;
    }

    .inception-item .value {
        font-size: 0.92rem;
        font-weight: 600;
        word-break: break-word;
    }

    .path-note {
        margin-top: 20px;
        font-size: 0.8rem;
        color: var(--muted);
    }
</style>
@endpush

@section('content')
    <div class="inception-page">
        <section class="card panel">
            <h2>Inception choices</h2>
            <p class="sub">Discovery decisions synced from the PRD into <code>.larapilot/choices.yaml</code>. Run <code>/larapilot-inception</code>, then refresh with <code>larapilot:choices-set --from-prd</code>.</p>

            @if (($inception ?? []) === [])
                <div class="empty" style="padding: 24px;">No choices yet. Run inception, then <code>larapilot:choices-set --from-prd</code>.</div>
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
        </section>
    </div>
@endsection
