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

    .board-scroll {
        width: 100%;
    }

    @media (max-width: 768px) {
        .board-scroll {
            margin: 0 -20px;
            padding: 0 20px 4px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scroll-snap-type: x proximity;
            scrollbar-width: thin;
        }

        .board {
            display: flex;
            flex-wrap: nowrap;
            gap: 16px;
            align-items: stretch;
            width: max-content;
            min-width: 100%;
        }

        .column {
            flex: 0 0 min(85vw, 300px);
            scroll-snap-align: start;
        }
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

    .column-stats {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .column-count {
        color: var(--muted);
        font-weight: 600;
        font-size: 0.72rem;
        text-transform: none;
        letter-spacing: normal;
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

    .spec-badges {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .points {
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--accent);
        background: var(--accent-soft);
        padding: 2px 8px;
        border-radius: 999px;
    }

    .task-progress {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 10px;
    }

    .task-progress-track {
        flex: 1;
        height: 5px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--border) 70%, transparent);
        overflow: hidden;
    }

    .task-progress-fill {
        height: 100%;
        border-radius: inherit;
        background: var(--status-done);
        transition: width 0.2s ease;
    }

    .task-progress-label {
        color: var(--muted);
        font-size: 0.72rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .merge-commit {
        margin-top: 8px;
        font-size: 0.72rem;
        font-weight: 700;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        color: var(--status-done);
    }

    .column-empty {
        padding: 16px;
        text-align: center;
        color: var(--muted);
        font-size: 0.85rem;
    }

    .mockup-indicator {
        margin-top: 8px;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #7c3aed;
    }

    .feedback-indicator {
        margin-top: 8px;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #b45309;
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
        <div class="board-scroll">
        <section class="board">
            @foreach ($statusOrder as $status)
                @php
                    $items = $columns[$status] ?? [];
                    $columnPoints = array_sum(array_map(
                        fn (array $spec): int => max(0, (int) ($spec['points'] ?? 0)),
                        $items
                    ));
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
                        <div class="column-stats">
                            <span class="column-count">{{ count($items) }} specs</span>
                            @if ($columnPoints > 0)
                                <span class="points">{{ $columnPoints }} SP</span>
                            @endif
                        </div>
                    </div>
                    <div class="column-body">
                        @forelse ($items as $spec)
                            <a class="spec-card" href="{{ route('larapilot.dashboard.spec', $spec['code']) }}">
                                <div class="spec-meta">
                                    <strong>{{ $spec['code'] }}</strong>
                                    <div class="spec-badges">
                                        @if (! empty($spec['points']))
                                            <span class="points">{{ $spec['points'] }} SP</span>
                                        @endif
                                        @if (! empty($spec['priority']))
                                            @php
                                                $priorityClass = match (strtoupper((string) $spec['priority'])) {
                                                    'CRITICAL' => 'priority-critical',
                                                    'HIGH' => 'priority-high',
                                                    'MEDIUM' => 'priority-medium',
                                                    'LOW' => 'priority-low',
                                                    default => 'priority-medium',
                                                };
                                            @endphp
                                            <span class="priority {{ $priorityClass }}">{{ $spec['priority'] }}</span>
                                        @endif
                                    </div>
                                </div>
                                <h3>{{ $spec['title'] ?? 'Untitled' }}</h3>
                                @if (! empty($spec['mockups']['available']))
                                    <div class="mockup-indicator">Mockup</div>
                                @endif
                                @if (! empty($spec['feedback']['entry_count']))
                                    <div class="feedback-indicator">
                                        {{ $spec['feedback']['entry_count'] }} comment{{ $spec['feedback']['entry_count'] === 1 ? '' : 's' }}
                                        @if (! empty($spec['feedback']['blocking_count']))
                                            · {{ $spec['feedback']['blocking_count'] }} blocking
                                        @endif
                                    </div>
                                @endif
                                @if (! empty($spec['epic']['title']))
                                    <p>{{ $spec['epic']['title'] }}</p>
                                @endif
                                @php
                                    $taskTotal = (int) ($spec['tasks']['total'] ?? 0);
                                    $taskDone = (int) ($spec['tasks']['done'] ?? 0);
                                    $taskPercent = $taskTotal > 0 ? round($taskDone / $taskTotal * 100) : 0;
                                @endphp
                                @if ($taskTotal > 0)
                                    <div class="task-progress" title="{{ $taskDone }} of {{ $taskTotal }} subtasks done">
                                        <div class="task-progress-track" aria-hidden="true">
                                            <div class="task-progress-fill" style="width: {{ $taskPercent }}%"></div>
                                        </div>
                                        <span class="task-progress-label">{{ $taskDone }}/{{ $taskTotal }}</span>
                                    </div>
                                @endif
                                @if (! empty($spec['merge_commit']['short_sha']) || ! empty($spec['merge_commit']['sha']))
                                    <div class="merge-commit" title="{{ $spec['merge_commit']['subject'] ?? 'Merge commit' }}">
                                        @if (! empty($spec['merge_commit']['url']))
                                            <a href="{{ $spec['merge_commit']['url'] }}" onclick="event.stopPropagation();" target="_blank" rel="noopener noreferrer">MR {{ $spec['merge_commit']['short_sha'] ?? substr((string) $spec['merge_commit']['sha'], 0, 7) }}</a>
                                        @else
                                            MR {{ $spec['merge_commit']['short_sha'] ?? substr((string) $spec['merge_commit']['sha'], 0, 7) }}
                                        @endif
                                    </div>
                                @endif
                            </a>
                        @empty
                            <div class="column-empty">No specs</div>
                        @endforelse
                    </div>
                </article>
            @endforeach
        </section>
        </div>
    @endif
@endsection
