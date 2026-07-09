@extends('larapilot::dashboard.layout')

@section('title', $spec['code'] ?? 'Spec')

@push('styles')
<style>
    .spec-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .spec-header h2 {
        margin: 0 0 6px;
        font-size: 1.35rem;
    }

    .spec-header p {
        margin: 0;
        color: var(--muted);
    }

    .spec-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 20px;
        padding: 20px 24px 28px;
    }

    .panel {
        padding: 20px 22px;
    }

    .panel h3 {
        margin: 0 0 14px;
        font-size: 1rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--muted);
    }

    .tasks {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .task {
        border: 1px solid var(--border);
        border-radius: 10px;
        overflow: hidden;
    }

    .task-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 14px;
        background: color-mix(in srgb, var(--surface) 90%, var(--bg));
        border-bottom: 1px solid var(--border);
        flex-wrap: wrap;
    }

    .task-header strong {
        font-size: 0.92rem;
    }

    .task-type {
        color: var(--muted);
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-weight: 600;
    }

    .task-body {
        padding: 14px 16px;
    }

    .task-status-done {
        color: var(--status-done);
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .task-status-todo {
        color: var(--muted);
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .back-link {
        display: inline-block;
        margin-bottom: 16px;
        font-size: 0.875rem;
    }
</style>
@endpush

@section('content')
    <a class="back-link" href="{{ route('larapilot.dashboard.index') }}">← Back to board</a>

    <article class="card">
        <header class="spec-header">
            <div>
                <h2>{{ $spec['code'] }} — {{ $spec['title'] ?? 'Untitled' }}</h2>
                @if (! empty($spec['epic']['title']))
                    <p>Epic: {{ $spec['epic']['title'] }}</p>
                @endif
            </div>
            <div>
                @php
                    $status = strtoupper((string) ($spec['status'] ?? 'TODO'));
                    $badgeClass = match ($status) {
                        'TODO' => 'badge-todo',
                        'PLANNED' => 'badge-planned',
                        'IN PROGRESS' => 'badge-in-progress',
                        'REVIEW' => 'badge-review',
                        'DONE' => 'badge-done',
                        default => 'badge-todo',
                    };
                @endphp
                <span class="badge {{ $badgeClass }}">{{ $status }}</span>
            </div>
        </header>

        <div class="spec-grid">
            <section class="card panel">
                <h3>User story</h3>
                <div class="markdown">{!! $spec_html !!}</div>
            </section>

            @if ($plan_html)
                <section class="card panel">
                    <h3>Technical plan</h3>
                    <div class="markdown">{!! $plan_html !!}</div>
                </section>
            @endif

            <section class="card panel">
                <h3>Tasks ({{ count($tasks) }})</h3>
                @if ($tasks === [])
                    <p class="empty" style="padding: 12px 0; text-align: left;">No plan tasks yet. Run <code>/larapilot-plan {{ $spec['code'] }}</code>.</p>
                @else
                    <div class="tasks">
                        @foreach ($tasks as $task)
                            <article class="task">
                                <div class="task-header">
                                    <div>
                                        <strong>{{ $task['id'] ?? 'TASK' }} — {{ $task['title'] ?? 'Untitled' }}</strong>
                                        @if (! empty($task['type']))
                                            <div class="task-type">{{ $task['type'] }}</div>
                                        @endif
                                    </div>
                                    @if (strtoupper((string) ($task['status'] ?? 'TODO')) === 'DONE')
                                        <span class="task-status-done">Done</span>
                                    @else
                                        <span class="task-status-todo">{{ $task['status'] ?? 'TODO' }}</span>
                                    @endif
                                </div>
                                @if (! empty($task['body_html']))
                                    <div class="task-body markdown">{!! $task['body_html'] !!}</div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </article>
@endsection
