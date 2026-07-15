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

    .mockup-badge {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #7c3aed;
        background: color-mix(in srgb, #7c3aed 12%, transparent);
        padding: 4px 10px;
        border-radius: 999px;
    }

    .mockup-preview {
        border: 1px solid var(--border);
        border-radius: 10px;
        overflow: hidden;
        background: color-mix(in srgb, var(--surface) 92%, var(--bg));
    }

    .mockup-preview iframe {
        display: block;
        width: 100%;
        min-height: 420px;
        border: 0;
        background: #fff;
    }

    .mockup-screens {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 14px;
    }

    .mockup-screen-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 999px;
        border: 1px solid var(--border);
        font-size: 0.82rem;
        font-weight: 600;
        color: inherit;
        text-decoration: none;
        transition: border-color 0.15s ease, color 0.15s ease;
    }

    .mockup-screen-link:hover {
        border-color: #7c3aed;
        color: #7c3aed;
        text-decoration: none;
    }

    .mockup-path {
        margin-top: 10px;
        color: var(--muted);
        font-size: 0.82rem;
    }

    .feedback-badge {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #b45309;
        background: color-mix(in srgb, #f59e0b 14%, transparent);
        padding: 4px 10px;
        border-radius: 999px;
    }

    .feedback-alert {
        margin: 0 24px 0;
        padding: 12px 16px;
        border-radius: 10px;
        font-size: 0.92rem;
    }

    .feedback-alert--success {
        background: color-mix(in srgb, #10b981 12%, transparent);
        border: 1px solid color-mix(in srgb, #10b981 35%, var(--border));
        color: #047857;
    }

    .feedback-alert--error {
        background: color-mix(in srgb, #ef4444 10%, transparent);
        border: 1px solid color-mix(in srgb, #ef4444 35%, var(--border));
        color: #b91c1c;
    }

    .feedback-form {
        display: grid;
        gap: 12px;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid var(--border);
    }

    .feedback-form label {
        display: grid;
        gap: 6px;
        font-size: 0.88rem;
        font-weight: 600;
    }

    .feedback-form input[type="text"],
    .feedback-form textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--surface);
        color: var(--text);
        font: inherit;
    }

    .feedback-form textarea {
        min-height: 120px;
        resize: vertical;
    }


    .feedback-checkbox {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 0.88rem;
        font-weight: 500;
    }

    .feedback-submit {
        border: 0;
        border-radius: 999px;
        padding: 10px 18px;
        background: var(--accent);
        color: #fff;
        font-weight: 700;
        cursor: pointer;
    }

    .feedback-closed {
        margin-top: 12px;
        color: var(--muted);
        font-size: 0.88rem;
    }

    .feedback-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .feedback-accordion {
        border: 1px solid var(--border);
        border-radius: 10px;
        overflow: hidden;
        background: color-mix(in srgb, var(--surface) 92%, var(--bg));
    }

    .feedback-accordion--blocking {
        border-color: color-mix(in srgb, #f59e0b 45%, var(--border));
        background: color-mix(in srgb, #f59e0b 6%, var(--surface));
    }

    .feedback-accordion[open] {
        border-color: color-mix(in srgb, var(--accent) 45%, var(--border));
    }

    .feedback-accordion-summary {
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

    .feedback-accordion-summary::-webkit-details-marker,
    .feedback-accordion-summary::marker {
        display: none;
        content: '';
    }

    .feedback-accordion-title {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        min-width: 0;
        flex: 1;
    }

    .feedback-accordion-chevron {
        flex-shrink: 0;
        width: 18px;
        height: 18px;
        margin-top: 2px;
        color: var(--muted);
        transition: transform 0.15s ease, color 0.15s ease;
    }

    .feedback-accordion[open] .feedback-accordion-chevron {
        transform: rotate(90deg);
        color: var(--accent);
    }

    .feedback-accordion-headline {
        min-width: 0;
    }

    .feedback-accordion-headline strong {
        font-size: 0.92rem;
    }

    .feedback-accordion-preview {
        margin-top: 4px;
        color: var(--muted);
        font-size: 0.82rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }

    .feedback-accordion:not([open]) .feedback-accordion-preview {
        display: block;
    }

    .feedback-accordion[open] .feedback-accordion-preview {
        display: none;
    }

    .feedback-accordion-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .feedback-status {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--muted);
    }

    .feedback-rework-badge {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #b45309;
        background: color-mix(in srgb, #f59e0b 18%, transparent);
        padding: 3px 8px;
        border-radius: 999px;
        white-space: nowrap;
    }

    .feedback-accordion-panel {
        padding: 0 16px 16px;
        border-top: 1px solid var(--border);
    }

    .feedback-accordion:not([open]) .feedback-accordion-panel {
        display: none;
    }

    .feedback-body {
        padding: 14px 0 0;
    }

    .feedback-form label.required::after {
        content: ' *';
        color: #ef4444;
        font-weight: 700;
    }

    .md-editor {
        display: grid;
        gap: 0;
        border: 1px solid var(--border);
        border-radius: 8px;
        overflow: hidden;
        background: var(--surface);
    }

    .md-toolbar {
        display: flex;
        align-items: center;
        gap: 2px;
        padding: 6px 8px;
        border-bottom: 1px solid var(--border);
        background: color-mix(in srgb, var(--surface) 88%, var(--bg));
        flex-wrap: wrap;
    }

    .md-toolbar button {
        border: 0;
        background: transparent;
        color: var(--muted);
        width: 30px;
        height: 28px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.82rem;
        font-weight: 700;
        font-family: inherit;
    }

    .md-toolbar button:hover,
    .md-toolbar button.is-active {
        background: var(--accent-soft);
        color: var(--accent);
    }

    .md-toolbar-divider {
        width: 1px;
        height: 18px;
        background: var(--border);
        margin: 0 4px;
    }

    .md-editor textarea {
        width: 100%;
        min-height: 120px;
        padding: 12px;
        border: 0;
        border-radius: 0;
        background: transparent;
        color: var(--text);
        font: inherit;
        resize: vertical;
    }

    .md-editor textarea:focus {
        outline: none;
    }

    .md-preview {
        display: none;
        padding: 12px;
        border-top: 1px solid var(--border);
        min-height: 120px;
        font-size: 0.92rem;
    }

    .md-editor.is-preview .md-preview {
        display: block;
    }

    .md-editor.is-preview textarea {
        display: none;
    }

    .feedback-form-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 4px;
    }

    .feedback-form-footer-left {
        display: flex;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
        min-width: 0;
        flex: 1;
    }

    .feedback-form-log {
        color: var(--muted);
        font-size: 0.78rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 280px;
    }

    .feedback-form-log code {
        font-size: 0.76rem;
    }

    .feedback-form-blocking-hint {
        color: #b45309;
        font-size: 0.76rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .field-error {
        color: #b91c1c;
        font-size: 0.78rem;
        font-weight: 500;
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
                @if (! empty($mockups))
                    <span class="mockup-badge" title="{{ count($mockups['screens'] ?? []) }} screen(s)">Mockup</span>
                @endif
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
                @if (! empty($feedback['enabled']) && ! empty($feedback['blocking_count']))
                    <span class="feedback-badge" title="Blocking comments">{{ $feedback['blocking_count'] }} blocking</span>
                @endif
            </div>
        </header>

        @if (session('larapilot_success'))
            <p class="feedback-alert feedback-alert--success">{{ session('larapilot_success') }}</p>
        @endif
        @if (session('larapilot_error'))
            <p class="feedback-alert feedback-alert--error">{{ session('larapilot_error') }}</p>
        @endif

        <div class="spec-grid">
            <section class="card panel">
                <h3>User story</h3>
                <div class="markdown">{!! $spec_html !!}</div>
            </section>

            @if (! empty($mockups))
                <section class="card panel">
                    <h3>Mockups</h3>
                    @if (! empty($mockups['entry_url']))
                        <div class="mockup-preview">
                            <iframe
                                src="{{ $mockups['entry_url'] }}"
                                title="Mockup preview for {{ $spec['code'] }}"
                                loading="lazy"
                            ></iframe>
                        </div>
                    @endif
                    @if (! empty($mockups['screens']))
                        <div class="mockup-screens">
                            @foreach ($mockups['screens'] as $screen)
                                @if (! empty($screen['url']))
                                    <a class="mockup-screen-link" href="{{ $screen['url'] }}" target="_blank" rel="noopener noreferrer">
                                        {{ $screen['label'] ?? $screen['file'] }}
                                    </a>
                                @else
                                    <span class="mockup-screen-link">{{ $screen['label'] ?? $screen['file'] }}</span>
                                @endif
                            @endforeach
                        </div>
                    @endif
                    <p class="mockup-path">
                        Artifacts in <code>{{ $mockups['path'] ?? '' }}</code>
                        @if (empty($mockups['browsable']))
                            — preview route disabled in this environment
                        @endif
                    </p>
                </section>
            @endif

            @if (! empty($feedback['enabled']))
                <section class="card panel">
                    <h3>Internal feedback ({{ $feedback['entry_count'] ?? 0 }})</h3>
                    @if (! empty($feedback['entries']))
                        <div class="feedback-list" data-exclusive-accordion>
                            @foreach ($feedback['entries'] as $entry)
                                <details class="feedback-accordion @if (! empty($entry['blocks_merge'])) feedback-accordion--blocking @endif">
                                    <summary class="feedback-accordion-summary" aria-label="Toggle comment from {{ $entry['author'] }}">
                                        <div class="feedback-accordion-title">
                                            <svg class="feedback-accordion-chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                            </svg>
                                            <div class="feedback-accordion-headline">
                                                <strong>{{ $entry['author'] }}</strong>
                                                <span style="color: var(--muted); font-weight: 500;"> · {{ $entry['at'] }}</span>
                                                @if (! empty($entry['preview']))
                                                    <div class="feedback-accordion-preview">{{ $entry['preview'] }}</div>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="feedback-accordion-meta">
                                            @if (! empty($entry['blocks_merge']))
                                                <span class="feedback-rework-badge">Needs rework</span>
                                            @endif
                                            <span class="feedback-status">{{ $entry['status'] }}</span>
                                        </div>
                                    </summary>
                                    <div class="feedback-accordion-panel">
                                        <div class="feedback-body markdown">{!! $entry['body_html'] !!}</div>
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    @else
                        <p class="empty" style="padding: 12px 0; text-align: left;">No comments yet. PM and dev can log decisions and questions here until the story is DONE.</p>
                    @endif

                    @if (! empty($feedback['writable']))
                        <form class="feedback-form" method="post" action="{{ route('larapilot.dashboard.spec.comments.store', $spec['code']) }}">
                            @csrf
                            <label class="required">
                                Author
                                <input
                                    type="text"
                                    name="author"
                                    value="{{ old('author') }}"
                                    maxlength="80"
                                    placeholder="PM"
                                    required
                                    autocomplete="off"
                                >
                                @error('author')
                                    <span class="field-error">{{ $message }}</span>
                                @enderror
                            </label>
                            <label class="required">
                                Comment
                                <div class="md-editor" data-md-editor>
                                    <div class="md-toolbar" role="toolbar" aria-label="Markdown formatting">
                                        <button type="button" data-md-action="bold" title="Bold"><strong>B</strong></button>
                                        <button type="button" data-md-action="italic" title="Italic"><em>I</em></button>
                                        <button type="button" data-md-action="code" title="Inline code">&lt;&gt;</button>
                                        <span class="md-toolbar-divider" aria-hidden="true"></span>
                                        <button type="button" data-md-action="ul" title="Bullet list">•</button>
                                        <button type="button" data-md-action="link" title="Link">🔗</button>
                                        <span class="md-toolbar-divider" aria-hidden="true"></span>
                                        <button type="button" data-md-action="preview" title="Toggle preview">👁</button>
                                    </div>
                                    <textarea
                                        name="message"
                                        required
                                        maxlength="10000"
                                        placeholder="Scope clarification, review note, implementation question…"
                                        data-md-input
                                    >{{ old('message') }}</textarea>
                                    <div class="md-preview markdown" data-md-preview aria-hidden="true"></div>
                                </div>
                                @error('message')
                                    <span class="field-error">{{ $message }}</span>
                                @enderror
                            </label>
                            <div class="feedback-form-footer">
                                <div class="feedback-form-footer-left">
                                    <label class="feedback-checkbox">
                                        <input type="checkbox" name="blocks_merge" value="1" @checked(old('blocks_merge'))>
                                        Blocks merge / needs rework
                                    </label>
                                    <span class="feedback-form-log" title="{{ $feedback['path'] ?? '' }}">
                                        <code>{{ $feedback['path_short'] ?? $feedback['path'] ?? '' }}</code>
                                    </span>
                                    @if (! empty($feedback['blocking_count']))
                                        <span class="feedback-form-blocking-hint">{{ $feedback['blocking_count'] }} blocking</span>
                                    @endif
                                </div>
                                <button type="submit" class="feedback-submit">Add comment</button>
                            </div>
                        </form>
                    @else
                        <p class="feedback-closed">Comments are closed because this user story is DONE.</p>
                    @endif
                </section>
            @endif

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
        var selector = accordion.classList.contains('feedback-list')
            ? '.feedback-accordion'
            : '.task-accordion';

        accordion.querySelectorAll(selector).forEach(function (item) {
            item.addEventListener('toggle', function () {
                if (! item.open) {
                    return;
                }

                accordion.querySelectorAll(selector).forEach(function (other) {
                    if (other !== item) {
                        other.open = false;
                    }
                });
            });
        });
    });

    document.querySelectorAll('[data-md-editor]').forEach(function (editor) {
        var textarea = editor.querySelector('[data-md-input]');
        var preview = editor.querySelector('[data-md-preview]');

        if (! textarea || ! preview) {
            return;
        }

        function wrapSelection(before, after, placeholder) {
            var start = textarea.selectionStart;
            var end = textarea.selectionEnd;
            var selected = textarea.value.slice(start, end) || placeholder || '';
            var next = textarea.value.slice(0, start) + before + selected + after + textarea.value.slice(end);
            textarea.value = next;
            var cursor = start + before.length + selected.length + after.length;
            textarea.focus();
            textarea.setSelectionRange(start + before.length, start + before.length + selected.length);
            renderPreview();
        }

        function prefixLines(prefix) {
            var start = textarea.selectionStart;
            var end = textarea.selectionEnd;
            var value = textarea.value;
            var lineStart = value.lastIndexOf('\n', start - 1) + 1;
            var lineEnd = value.indexOf('\n', end);
            if (lineEnd === -1) {
                lineEnd = value.length;
            }
            var block = value.slice(lineStart, lineEnd);
            var lines = block.split('\n').map(function (line) {
                return line.startsWith(prefix) ? line : prefix + line;
            });
            textarea.value = value.slice(0, lineStart) + lines.join('\n') + value.slice(lineEnd);
            textarea.focus();
            renderPreview();
        }

        function renderPreview() {
            var text = textarea.value;
            preview.innerHTML = text.trim() === ''
                ? '<p style="color: var(--muted); margin: 0;">Preview will appear here…</p>'
                : window.larapilotMarkdownPreview(text);
        }

        editor.querySelectorAll('[data-md-action]').forEach(function (button) {
            button.addEventListener('click', function () {
                var action = button.getAttribute('data-md-action');

                if (action === 'preview') {
                    editor.classList.toggle('is-preview');
                    button.classList.toggle('is-active', editor.classList.contains('is-preview'));
                    renderPreview();
                    return;
                }

                if (action === 'bold') {
                    wrapSelection('**', '**', 'bold text');
                } else if (action === 'italic') {
                    wrapSelection('*', '*', 'italic text');
                } else if (action === 'code') {
                    wrapSelection('`', '`', 'code');
                } else if (action === 'ul') {
                    prefixLines('- ');
                } else if (action === 'link') {
                    wrapSelection('[', '](https://)', 'label');
                }
            });
        });

        textarea.addEventListener('input', function () {
            if (editor.classList.contains('is-preview')) {
                renderPreview();
            }
        });
    });

    window.larapilotMarkdownPreview = function (text) {
        var escaped = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        return escaped
            .replace(/^### (.+)$/gm, '<h3>$1</h3>')
            .replace(/^## (.+)$/gm, '<h2>$1</h2>')
            .replace(/^# (.+)$/gm, '<h1>$1</h1>')
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.+?)\*/g, '<em>$1</em>')
            .replace(/`([^`]+)`/g, '<code>$1</code>')
            .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>')
            .replace(/^- (.+)$/gm, '<li>$1</li>')
            .replace(/(<li>.*<\/li>\n?)+/g, function (match) {
                return '<ul>' + match + '</ul>';
            })
            .replace(/\n\n/g, '</p><p>')
            .replace(/^(?!<[hulo])/gm, '<p>')
            .replace(/<\/p><p><\/p>/g, '</p>');
    };
</script>
@endpush
