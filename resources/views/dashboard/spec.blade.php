@extends('larapilot::dashboard.layout')

@section('title', $spec['code'] ?? 'Spec')

@push('styles')
<style>
    .spec-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px 16px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: var(--muted);
        font-size: 0.86rem;
        font-weight: 500;
    }

    .back-link:hover { color: var(--accent); text-decoration: none; }
    .back-link .icon { width: 16px; height: 16px; }

    .spec-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px 18px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }

    .spec-header > :first-child { min-width: 0; flex: 1 1 320px; }

    .spec-code {
        color: var(--muted);
        font-family: var(--mono);
        font-size: 0.8rem;
        font-weight: 600;
    }

    .spec-header h2 {
        margin: 4px 0 6px;
        font-size: 1.5rem;
        letter-spacing: -0.022em;
        overflow-wrap: anywhere;
    }

    .spec-header p {
        margin: 0;
        color: var(--muted);
        font-size: 0.9rem;
    }

    .spec-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 16px;
    }

    .spec-grid .panel h3 {
        margin: 0 0 14px;
        color: var(--muted);
        font-size: 0.72rem;
        font-weight: 650;
        letter-spacing: 0.09em;
        text-transform: uppercase;
    }

    .spec-badges {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }

    .spec-badges .points { padding: 3px 10px; font-size: 0.7rem; }

    .merge-commit-block {
        margin-top: 8px !important;
        font-size: 0.82rem !important;
        overflow-wrap: anywhere;
    }

    .merge-commit-block a,
    .merge-commit-block code {
        font-family: var(--mono);
        font-weight: 600;
    }

    .mockup-badge,
    .feedback-badge {
        display: inline-flex;
        align-items: center;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 0.7rem;
        font-weight: 650;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .mockup-badge {
        background: color-mix(in srgb, var(--violet-fill) 14%, transparent);
        color: var(--violet);
    }

    .feedback-badge {
        background: color-mix(in srgb, var(--warn-fill) 15%, transparent);
        color: var(--warn);
    }

    /* ---- accordions: tasks and feedback share one shape ---- */
    .tasks,
    .feedback-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .task-accordion,
    .feedback-accordion {
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--surface-2);
        overflow: hidden;
        transition: border-color 0.14s ease;
    }

    .feedback-accordion--blocking {
        border-color: color-mix(in srgb, var(--warn-fill) 45%, var(--border));
        background: color-mix(in srgb, var(--warn-fill) 7%, var(--surface));
    }

    .task-accordion[open],
    .feedback-accordion[open] {
        border-color: color-mix(in srgb, var(--accent) 45%, var(--border));
        background: var(--surface);
    }

    .task-accordion-summary,
    .feedback-accordion-summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px 12px;
        padding: 12px 14px;
        cursor: pointer;
        list-style: none;
        flex-wrap: wrap;
        user-select: none;
    }

    .task-accordion-summary::-webkit-details-marker,
    .feedback-accordion-summary::-webkit-details-marker { display: none; }

    .task-accordion-summary::marker,
    .feedback-accordion-summary::marker { content: ''; }

    .task-accordion-title,
    .feedback-accordion-title {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        min-width: 0;
        flex: 1 1 220px;
    }

    .task-accordion-chevron,
    .feedback-accordion-chevron {
        flex-shrink: 0;
        width: 17px;
        height: 17px;
        margin-top: 2px;
        color: var(--muted);
        transition: transform 0.15s ease, color 0.15s ease;
    }

    .task-accordion[open] .task-accordion-chevron,
    .feedback-accordion[open] .feedback-accordion-chevron {
        transform: rotate(90deg);
        color: var(--accent);
    }

    .task-accordion-summary strong,
    .feedback-accordion-headline strong {
        font-size: 0.9rem;
        font-weight: 600;
    }

    .task-accordion-panel,
    .feedback-accordion-panel {
        padding: 0 16px 16px;
        border-top: 1px solid var(--border);
    }

    .task-accordion:not([open]) .task-accordion-panel,
    .feedback-accordion:not([open]) .feedback-accordion-panel { display: none; }

    .task-type {
        margin-top: 2px;
        color: var(--muted);
        font-size: 0.7rem;
        font-weight: 650;
        letter-spacing: 0.07em;
        text-transform: uppercase;
    }

    .task-body { padding: 14px 0 0; }

    .task-status-done,
    .task-status-todo {
        font-size: 0.7rem;
        font-weight: 650;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .task-status-done { color: var(--ok); }
    .task-status-todo { color: var(--muted); }

    .task-commit {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 3px;
        min-width: 0;
    }

    .task-commit a,
    .task-commit code {
        padding: 0;
        background: transparent;
        font-family: var(--mono);
        font-size: 0.75rem;
        font-weight: 600;
    }

    .task-commit-subject {
        max-width: 240px;
        color: var(--muted);
        font-size: 0.72rem;
        text-align: right;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .mockup-preview {
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        overflow: hidden;
    }

    .mockup-preview iframe {
        display: block;
        width: 100%;
        min-height: 440px;
        border: 0;
        background: #fff;
    }

    .mockup-screens {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 14px;
    }

    .mockup-screen-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 32px;
        padding: 0 13px;
        border: 1px solid var(--border-strong);
        border-radius: 999px;
        color: var(--text);
        font-size: 0.82rem;
        font-weight: 550;
        text-decoration: none;
        transition: border-color 0.15s ease, color 0.15s ease;
    }

    a.mockup-screen-link:hover {
        border-color: var(--accent);
        color: var(--accent);
        text-decoration: none;
    }

    .mockup-screen-meta {
        color: var(--muted);
        font-size: 0.72rem;
        font-weight: 500;
    }

    a.mockup-screen-link:hover .mockup-screen-meta { color: var(--accent); }

    .mockup-path {
        margin: 12px 0 0;
        color: var(--muted);
        font-size: 0.82rem;
    }

    .spec-empty {
        margin: 0;
        padding: 0;
        color: var(--muted);
        text-align: left;
        font-size: 0.9rem;
    }

    /* ---- feedback ---- */
    .feedback-accordion-headline { min-width: 0; }
    .feedback-when { color: var(--muted); font-weight: 500; }

    .feedback-accordion-preview {
        margin-top: 3px;
        max-width: 100%;
        color: var(--muted);
        font-size: 0.82rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .feedback-accordion[open] .feedback-accordion-preview { display: none; }

    .feedback-accordion-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .feedback-status {
        color: var(--muted);
        font-size: 0.7rem;
        font-weight: 650;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .feedback-rework-badge {
        padding: 2px 9px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--warn-fill) 17%, transparent);
        color: var(--warn);
        font-size: 0.66rem;
        font-weight: 650;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .feedback-body { padding: 14px 0 0; }

    .feedback-form {
        display: grid;
        gap: 14px;
        margin-top: 18px;
        padding-top: 18px;
        border-top: 1px solid var(--border);
    }

    .feedback-form > label {
        display: grid;
        gap: 6px;
        font-size: 0.86rem;
        font-weight: 600;
    }

    .feedback-form input[type="text"] { max-width: 320px; }

    .md-editor {
        display: grid;
        border: 1px solid var(--border-strong);
        border-radius: var(--radius-sm);
        background: var(--surface);
        overflow: hidden;
        transition: border-color 0.14s ease, box-shadow 0.14s ease;
    }

    .md-editor:focus-within {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 22%, transparent);
    }

    .md-toolbar {
        display: flex;
        align-items: center;
        gap: 2px;
        padding: 5px 8px;
        border-bottom: 1px solid var(--border);
        background: var(--surface-2);
        flex-wrap: wrap;
    }

    .md-toolbar button {
        width: 34px;
        height: 32px;
        border: 0;
        border-radius: var(--radius-xs);
        background: transparent;
        color: var(--muted);
        font-family: inherit;
        font-size: 0.82rem;
        font-weight: 650;
        cursor: pointer;
    }

    .md-toolbar button:hover,
    .md-toolbar button.is-active {
        background: var(--accent-soft);
        color: var(--accent-strong);
    }

    .md-toolbar-divider {
        width: 1px;
        height: 18px;
        margin: 0 4px;
        background: var(--border);
    }

    .md-editor textarea {
        width: 100%;
        min-height: 130px;
        padding: 12px 14px;
        border: 0;
        border-radius: 0;
        background: transparent;
        color: var(--text);
        font: inherit;
        font-weight: 400;
        resize: vertical;
    }

    .md-editor textarea:focus { outline: none; }

    .md-preview {
        display: none;
        min-height: 130px;
        padding: 12px 14px;
        font-size: 0.92rem;
        font-weight: 400;
    }

    .md-editor.is-preview .md-preview { display: block; }
    .md-editor.is-preview textarea { display: none; }

    .feedback-form-footer {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    .feedback-form-options {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 0;
        flex: 1 1 240px;
    }

    .feedback-checkbox {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        width: fit-content;
        font-size: 0.88rem;
        font-weight: 500;
        cursor: pointer;
    }

    .feedback-checkbox input {
        width: 16px;
        height: 16px;
        margin: 0;
        flex-shrink: 0;
        accent-color: var(--accent);
    }

    .feedback-form-log {
        margin: 0;
        padding-left: 24px;
        color: var(--muted);
        font-size: 0.78rem;
        overflow-wrap: anywhere;
    }

    .feedback-form-log code { font-size: 0.76rem; }

    .feedback-form-blocking-hint {
        margin-left: 0.35em;
        color: var(--warn);
        font-size: 0.76rem;
        font-weight: 600;
    }

    .feedback-closed {
        margin: 14px 0 0;
        color: var(--muted);
        font-size: 0.88rem;
    }

    .field-error {
        color: var(--danger);
        font-size: 0.78rem;
        font-weight: 500;
    }
</style>
@endpush

@section('content')
    <div class="spec-topbar">
        <a class="back-link" href="{{ route('larapilot.dashboard.index') }}">@include('larapilot::dashboard.partials.icon', ['name' => 'back'])Back to board</a>
        <a class="btn ghost" href="{{ route('larapilot.dashboard.spec.download', $spec['code']) }}" title="The story, the plan, every task, the decisions and the comments in one Markdown file">@include('larapilot::dashboard.partials.icon', ['name' => 'download'])Download spec (.md)</a>
    </div>

    <article>
        <header class="spec-header">
            <div>
                <span class="spec-code">{{ $spec['code'] }}</span>
                <h2>{{ $spec['title'] ?? 'Untitled' }}</h2>
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
                @if (! empty($spec['points']))
                    <span class="points">{{ $spec['points'] }} SP</span>
                @endif
                @if (! empty($mockups))
                    <span class="mockup-badge" title="{{ count($mockups['screens'] ?? []) }} screen(s)">Mockup</span>
                @endif
                @if (! empty($feedback['enabled']) && ! empty($feedback['blocking_count']))
                    <span class="feedback-badge" title="Blocking comments">{{ $feedback['blocking_count'] }} blocking</span>
                @endif
            </div>
        </header>

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
                                @php
                                    $screenWhere = trim(implode(' · ', array_filter([
                                        (string) ($screen['flow'] ?? ''),
                                        (string) ($screen['style'] ?? ''),
                                    ], static fn (string $part): bool => $part !== '')));
                                @endphp
                                @if (! empty($screen['url']))
                                    <a class="mockup-screen-link" href="{{ $screen['url'] }}" target="_blank" rel="noopener noreferrer" title="{{ $screen['file'] ?? '' }}">
                                        {{ $screen['label'] ?? $screen['file'] }}
                                        @if ($screenWhere !== '')
                                            <span class="mockup-screen-meta">{{ $screenWhere }}</span>
                                        @endif
                                    </a>
                                @else
                                    <span class="mockup-screen-link" title="{{ $screen['file'] ?? '' }}">
                                        {{ $screen['label'] ?? $screen['file'] }}
                                        @if ($screenWhere !== '')
                                            <span class="mockup-screen-meta">{{ $screenWhere }}</span>
                                        @endif
                                    </span>
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
                                                <span class="feedback-when"> · {{ $entry['at'] }}</span>
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
                        <p class="spec-empty">No comments yet. PM and dev can log decisions and questions here until the story is DONE.</p>
                    @endif

                    @if (! empty($feedback['writable']))
                        <form class="feedback-form" method="post" action="{{ route('larapilot.dashboard.spec.comments.store', $spec['code']) }}">
                            @csrf
                            <label>
                                Author
                                <input
                                    class="control"
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
                            <label>
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
                                <div class="feedback-form-options">
                                    <label class="feedback-checkbox">
                                        <input type="checkbox" name="blocks_merge" value="1" @checked(old('blocks_merge'))>
                                        <span>Blocks merge / needs rework</span>
                                    </label>
                                    <p class="feedback-form-log" title="{{ $feedback['path'] ?? '' }}">
                                        <code>{{ $feedback['path_short'] ?? $feedback['path'] ?? '' }}</code>
                                        @if (! empty($feedback['blocking_count']))
                                            <span class="feedback-form-blocking-hint">· {{ $feedback['blocking_count'] }} blocking</span>
                                        @endif
                                    </p>
                                </div>
                                <button type="submit" class="btn feedback-submit">Add comment</button>
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
                @include('larapilot::dashboard.partials.decisions', [
                    'decisions' => $decisions ?? ['entry_count' => 0, 'groups' => null, 'entries' => []],
                    'embedded' => true,
                ])
            </section>

            <section class="card panel">
                <h3>Tasks ({{ count($tasks) }})</h3>
                @if ($tasks === [])
                    <p class="spec-empty">No plan tasks yet. Run <code>/larapilot-plan {{ $spec['code'] }}</code>.</p>
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
            : accordion.classList.contains('decisions-timeline')
                ? '.decision-entry'
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
