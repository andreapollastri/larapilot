@extends('larapilot::dashboard.layout')

@section('title', 'Board')

@push('styles')
<style>
    .metrics {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .metric {
        padding: 18px 20px;
    }

    .metric-label {
        color: var(--muted);
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-weight: 600;
    }

    .metric-value {
        margin-top: 6px;
        font-size: 1.75rem;
        font-weight: 700;
        line-height: 1;
    }

    .board {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        align-items: start;
    }

    .column {
        min-height: 120px;
    }

    .column-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 14px;
        border-bottom: 1px solid var(--border);
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .column-count {
        color: var(--muted);
        font-weight: 600;
    }

    .column-body {
        padding: 12px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .spec-card {
        display: block;
        padding: 12px 14px;
        border-radius: 10px;
        border: 1px solid var(--border);
        background: color-mix(in srgb, var(--surface) 92%, var(--bg));
        color: inherit;
        text-decoration: none;
        transition: border-color 0.15s ease, transform 0.15s ease;
    }

    .spec-card:hover {
        border-color: var(--accent);
        transform: translateY(-1px);
        text-decoration: none;
    }

    .spec-card h3 {
        margin: 0 0 6px;
        font-size: 0.95rem;
    }

    .spec-card p {
        margin: 0;
        color: var(--muted);
        font-size: 0.82rem;
    }

    .spec-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 8px;
    }

    .column-empty {
        padding: 16px;
        text-align: center;
        color: var(--muted);
        font-size: 0.85rem;
    }
</style>
@endpush

@section('content')
    <section class="metrics">
        <div class="card metric">
            <div class="metric-label">Total specs</div>
            <div class="metric-value">{{ $metrics['total'] ?? 0 }}</div>
        </div>
        <div class="card metric">
            <div class="metric-label">Done</div>
            <div class="metric-value">{{ $metrics['done'] ?? 0 }}</div>
        </div>
        <div class="card metric">
            <div class="metric-label">Completion</div>
            <div class="metric-value">{{ $metrics['completion_rate'] ?? 0 }}%</div>
        </div>
        <div class="card metric">
            <div class="metric-label">WIP</div>
            <div class="metric-value">{{ $metrics['wip'] ?? 0 }}</div>
        </div>
    </section>

    @if (($metrics['total'] ?? 0) === 0)
        <div class="card empty">
            <p>No backlog specs yet. Run <code>/larapilot-spec</code> to create user stories.</p>
        </div>
    @else
        <section class="board">
            @foreach ($statusOrder as $status)
                @php
                    $items = $columns[$status] ?? [];
                    $badgeClass = match (strtoupper($status)) {
                        'TODO' => 'badge-todo',
                        'PLANNED' => 'badge-planned',
                        'IN PROGRESS' => 'badge-in-progress',
                        'REVIEW' => 'badge-review',
                        'DONE' => 'badge-done',
                        default => 'badge-todo',
                    };
                @endphp
                <article class="card column">
                    <div class="column-header">
                        <span class="badge {{ $badgeClass }}">{{ $status }}</span>
                        <span class="column-count">{{ count($items) }}</span>
                    </div>
                    <div class="column-body">
                        @forelse ($items as $spec)
                            <a class="spec-card" href="{{ route('larapilot.dashboard.spec', $spec['code']) }}">
                                <div class="spec-meta">
                                    <strong>{{ $spec['code'] }}</strong>
                                    @if (! empty($spec['priority']))
                                        <span class="priority">{{ $spec['priority'] }}</span>
                                    @endif
                                </div>
                                <h3>{{ $spec['title'] ?? 'Untitled' }}</h3>
                                @if (! empty($spec['epic']['title']))
                                    <p>{{ $spec['epic']['title'] }}</p>
                                @endif
                            </a>
                        @empty
                            <div class="column-empty">No specs</div>
                        @endforelse
                    </div>
                </article>
            @endforeach
        </section>
    @endif
@endsection
