@php
    $journal = $decisions ?? ['entry_count' => 0, 'groups' => null, 'entries' => []];
    $grouped = is_array($journal['groups'] ?? null) && ($journal['groups'] ?? []) !== [];
    $flatEntries = $journal['entries'] ?? [];
    $embedded = ! empty($embedded);
    $hideSpecBadge = $embedded || ! empty($hide_spec_badge);
@endphp

<section @class(['decisions-panel', 'card' => ! $embedded]) id="decision-journal">
    <header class="decisions-header">
        <div>
            @if ($embedded)
                <h3>Decision journal ({{ $journal['entry_count'] ?? 0 }})</h3>
            @else
                <h2>Decision journal</h2>
            @endif
            <p class="decisions-sub">
                Append-only chronology from <code>{{ $journal['path_short'] ?? 'decisions.yaml' }}</code>
                @if (($journal['regression_count'] ?? 0) > 0)
                    · <span class="decisions-regression-hint">{{ $journal['regression_count'] }} topic(s) changed over time</span>
                @endif
            </p>
        </div>
        @if (! $embedded && ($journal['entry_count'] ?? 0) > 0)
            <span class="decisions-count">{{ $journal['entry_count'] }} decision(s)</span>
        @endif
    </header>

    @if (($journal['entry_count'] ?? 0) === 0)
        <p class="empty decisions-empty">
            No decisions recorded yet. Explicit choices are logged via
            <code>php artisan larapilot:decision-log</code> during inception and other phases.
        </p>
    @elseif ($grouped)
        <div class="decisions-groups">
            @foreach ($journal['groups'] as $group)
                <section class="decisions-group">
                    <h3 class="decisions-group-title">
                        @if (! empty($group['spec_code']))
                            <a href="{{ route('larapilot.dashboard.spec', $group['spec_code']) }}">{{ $group['label'] }}</a>
                        @else
                            {{ $group['label'] }}
                        @endif
                        <span class="decisions-group-count">{{ count($group['entries'] ?? []) }}</span>
                    </h3>
                    @include('larapilot::dashboard.partials.decisions-timeline', [
                        'entries' => $group['entries'] ?? [],
                        'hide_spec_badge' => $hideSpecBadge,
                    ])
                </section>
            @endforeach
        </div>
    @else
        @include('larapilot::dashboard.partials.decisions-timeline', [
            'entries' => $flatEntries,
            'hide_spec_badge' => $hideSpecBadge,
        ])
    @endif
</section>

@once
    @push('styles')
    <style>
        .decisions-panel {
            margin-top: 20px;
            padding: 20px 22px 24px;
        }

        .decisions-panel:not(.card) {
            margin-top: 0;
            padding: 0;
        }

        .decisions-panel:not(.card) .decisions-header h3 {
            margin: 0 0 14px;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--muted);
        }

        .decisions-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .decisions-header h2 {
            margin: 0 0 4px;
            font-size: 1.05rem;
        }

        .decisions-sub {
            margin: 0;
            color: var(--muted);
            font-size: 0.875rem;
        }

        .decisions-count {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--muted);
            padding: 4px 10px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: color-mix(in srgb, var(--border) 35%, transparent);
        }

        .decisions-regression-hint {
            color: #b45309;
            font-weight: 600;
        }

        .decisions-empty {
            padding: 8px 0 0;
            text-align: left;
        }

        .decisions-groups {
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        .decisions-group-title {
            margin: 0 0 10px;
            font-size: 0.92rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .decisions-group-title a {
            color: var(--text);
            text-decoration: none;
        }

        .decisions-group-title a:hover {
            color: var(--accent);
            text-decoration: underline;
        }

        .decisions-group-count {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--muted);
            padding: 2px 8px;
            border-radius: 999px;
            border: 1px solid var(--border);
        }

        .decisions-timeline {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .decision-entry {
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            background: color-mix(in srgb, var(--surface) 92%, var(--bg));
        }

        .decision-entry--superseded {
            opacity: 0.72;
        }

        .decision-entry--superseded .decision-entry-value {
            text-decoration: line-through;
        }

        .decision-entry[open] {
            border-color: color-mix(in srgb, var(--accent) 45%, var(--border));
        }

        .decision-entry-summary {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            cursor: pointer;
            list-style: none;
            flex-wrap: wrap;
            user-select: none;
        }

        .decision-entry-summary::-webkit-details-marker,
        .decision-entry-summary::marker {
            display: none;
            content: '';
        }

        .decision-entry-title {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            min-width: 0;
            flex: 1;
        }

        .decision-entry-chevron {
            flex-shrink: 0;
            width: 18px;
            height: 18px;
            margin-top: 2px;
            color: var(--muted);
            transition: transform 0.15s ease, color 0.15s ease;
        }

        .decision-entry[open] .decision-entry-chevron {
            transform: rotate(90deg);
            color: var(--accent);
        }

        .decision-entry-headline strong {
            font-size: 0.92rem;
        }

        .decision-entry-value {
            margin-top: 2px;
            font-size: 0.88rem;
            color: var(--text);
        }

        .decision-entry-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .decision-badge {
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 2px 8px;
            border-radius: 999px;
            border: 1px solid var(--border);
            color: var(--muted);
            white-space: nowrap;
        }

        .decision-badge--spec {
            border-color: color-mix(in srgb, var(--accent) 40%, var(--border));
            color: var(--accent);
            background: var(--accent-soft);
        }

        .decision-badge--superseded {
            border-color: #fcd34d;
            color: #92400e;
            background: #fef3c7;
        }

        .decision-badge--changed {
            border-color: #fdba74;
            color: #9a3412;
            background: #ffedd5;
        }

        .decision-entry-panel {
            padding: 0 16px 14px;
            border-top: 1px solid var(--border);
            font-size: 0.88rem;
        }

        .decision-entry:not([open]) .decision-entry-panel {
            display: none;
        }

        .decision-detail-grid {
            display: grid;
            gap: 8px;
            padding-top: 12px;
        }

        .decision-detail-row {
            display: grid;
            grid-template-columns: 110px 1fr;
            gap: 10px;
        }

        .decision-detail-label {
            color: var(--muted);
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding-top: 2px;
        }

        .decision-detail-value {
            margin: 0;
            word-break: break-word;
        }
    </style>
    @endpush
@endonce
