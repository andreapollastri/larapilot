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
        gap: 10px;
    }

    .task-accordion {
        border: 1px solid var(--border);
        border-radius: 10px;
        overflow: hidden;
        background: color-mix(in srgb, var(--surface) 92%, var(--bg));
    }

    .task-accordion[open] {
        border-color: color-mix(in srgb, var(--accent) 45%, var(--border));
    }

    .task-accordion-summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 14px;
        cursor: pointer;
        list-style: none;
        flex-wrap: wrap;
        user-select: none;
    }

    .task-accordion-summary::-webkit-details-marker {
        display: none;
    }

    .task-accordion-summary::marker {
        content: '';
    }

    .task-accordion-title {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        min-width: 0;
        flex: 1;
    }

    .task-accordion-chevron {
        flex-shrink: 0;
        width: 18px;
        height: 18px;
        margin-top: 2px;
        color: var(--muted);
        transition: transform 0.15s ease, color 0.15s ease;
    }

    .task-accordion[open] .task-accordion-chevron {
        transform: rotate(90deg);
        color: var(--accent);
    }

    .task-accordion-summary strong {
        font-size: 0.92rem;
    }

    .task-accordion-panel {
        padding: 0 16px 16px;
        border-top: 1px solid var(--border);
    }

    .task-accordion:not([open]) .task-accordion-panel {
        display: none;
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

    .task-commit {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 4px;
        min-width: 0;
    }

    .task-commit a,
    .task-commit code {
        font-size: 0.75rem;
        font-weight: 700;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }

    .task-commit-subject {
        color: var(--muted);
        font-size: 0.72rem;
        max-width: 220px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        text-align: right;
    }

    .back-link {
        display: inline-block;
        margin-bottom: 16px;
        font-size: 0.875rem;
    }

    .spec-badges {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .points {
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--accent);
        background: var(--accent-soft);
        padding: 4px 10px;
        border-radius: 999px;
    }

    .merge-commit {
        font-size: 0.72rem;
        font-weight: 700;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        color: var(--status-done);
    }

    .merge-commit-block {
        margin-top: 8px;
        color: var(--muted);
        font-size: 0.82rem;
    }

    .merge-commit-block a,
    .merge-commit-block code {
        font-weight: 700;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
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
                @if (! empty($spec['merge_commit']))
                    <p class="merge-commit-block">
                        Merged as
                        @if (! empty($spec['merge_commit']['url']))
                            <a href="{{ $spec['merge_commit']['url'] }}" target="_blank" rel="noopener noreferrer" title="{{ $spec['merge_commit']['subject'] ?? '' }}">
                                {{ $spec['merge_commit']['short_sha'] ?? substr((string) ($spec['merge_commit']['sha'] ?? ''), 0, 7) }}
                            </a>
                        @else
                            <code title="{{ $spec['merge_commit']['subject'] ?? '' }}">{{ $spec['merge_commit']['short_sha'] ?? substr((string) ($spec['merge_commit']['sha'] ?? ''), 0, 7) }}</code>
                        @endif
                        @if (! empty($spec['merge_commit']['subject']))
                            — {{ $spec['merge_commit']['subject'] }}
                        @endif
                    </p>
                @endif
            </div>
            <div class="spec-badges">
                @if (! empty($spec['points']))
                    <span class="points">{{ $spec['points'] }} SP</span>
                @endif
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
                    <div class="tasks" data-exclusive-accordion>
                        @foreach ($tasks as $task)
                            @php
                                $taskId = (string) ($task['id'] ?? 'TASK');
                                $isDone = strtoupper((string) ($task['status'] ?? 'TODO')) === 'DONE';
                            @endphp
                            <details class="task-accordion">
                                <summary class="task-accordion-summary" aria-label="Toggle {{ $taskId }} details">
                                    <div class="task-accordion-title">
                                        <svg class="task-accordion-chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                        </svg>
                                        <div>
                                            <strong>{{ $taskId }} — {{ $task['title'] ?? 'Untitled' }}</strong>
                                            @if (! empty($task['type']))
                                                <div class="task-type">{{ $task['type'] }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    @if ($isDone)
                                        <div class="task-commit">
                                            @if (! empty($task['commit']['url']))
                                                <a href="{{ $task['commit']['url'] }}" target="_blank" rel="noopener noreferrer" title="{{ $task['commit']['subject'] ?? '' }}" onclick="event.stopPropagation();">
                                                    {{ $task['commit']['short_sha'] ?? substr((string) ($task['commit']['sha'] ?? ''), 0, 7) }}
                                                </a>
                                            @elseif (! empty($task['commit']['sha']))
                                                <code title="{{ $task['commit']['subject'] ?? '' }}">{{ $task['commit']['short_sha'] ?? substr((string) $task['commit']['sha'], 0, 7) }}</code>
                                            @endif
                                            @if (! empty($task['commit']['subject']))
                                                <span class="task-commit-subject">{{ $task['commit']['subject'] }}</span>
                                            @endif
                                            <span class="task-status-done">Done</span>
                                        </div>
                                    @else
                                        <span class="task-status-todo">{{ $task['status'] ?? 'TODO' }}</span>
                                    @endif
                                </summary>
                                @if (! empty($task['body_html']))
                                    <div class="task-accordion-panel">
                                        <div class="task-body markdown">{!! $task['body_html'] !!}</div>
                                    </div>
                                @endif
                            </details>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </article>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-exclusive-accordion]').forEach(function (accordion) {
        accordion.querySelectorAll('.task-accordion').forEach(function (item) {
            item.addEventListener('toggle', function () {
                if (! item.open) {
                    return;
                }

                accordion.querySelectorAll('.task-accordion').forEach(function (other) {
                    if (other !== item) {
                        other.open = false;
                    }
                });
            });
        });
    });
</script>
@endpush
