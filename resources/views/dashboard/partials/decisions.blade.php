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
            margin-top: 22px;
            padding: 20px 18px 22px;
        }

        @media (min-width: 640px) {
            .decisions-panel { padding: 24px 26px 26px; }
        }

        .decisions-panel:not(.card) {
            margin-top: 0;
            padding: 0;
        }

        .decisions-panel:not(.card) .decisions-header h3 {
            margin: 0 0 6px;
            color: var(--muted);
            font-size: 0.72rem;
            font-weight: 650;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .decisions-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .decisions-header h2 {
            margin: 0 0 4px;
            font-size: 1.1rem;
        }

        .decisions-sub {
            margin: 0;
            color: var(--muted);
            font-size: 0.85rem;
        }

        .decisions-count {
            padding: 4px 11px;
            border: 1px solid var(--border);
            border-radius: 999px;
            background: var(--surface-2);
            color: var(--muted);
            font-size: 0.78rem;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
        }

        .decisions-regression-hint {
            color: var(--warn);
            font-weight: 600;
        }

        .decisions-empty {
            padding: 4px 0 0;
            text-align: left;
        }

        .decisions-empty p, p.decisions-empty { max-width: none; margin: 0; }

        .decisions-groups {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .decisions-group-title {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin: 0 0 10px;
            font-size: 0.92rem;
            font-weight: 600;
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
            padding: 1px 8px;
            border: 1px solid var(--border);
            border-radius: 999px;
            color: var(--muted);
            font-size: 0.72rem;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
        }

        .decisions-timeline {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .decision-entry {
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--surface-2);
            overflow: hidden;
            transition: border-color 0.14s ease;
        }

        .decision-entry--superseded { opacity: 0.7; }
        .decision-entry--superseded .decision-entry-value { text-decoration: line-through; }

        .decision-entry[open] {
            border-color: color-mix(in srgb, var(--accent) 45%, var(--border));
            background: var(--surface);
        }

        .decision-entry-summary {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px 12px;
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
            flex: 1 1 220px;
        }

        .decision-entry-chevron {
            flex-shrink: 0;
            width: 17px;
            height: 17px;
            margin-top: 2px;
            color: var(--muted);
            transition: transform 0.15s ease, color 0.15s ease;
        }

        .decision-entry[open] .decision-entry-chevron {
            transform: rotate(90deg);
            color: var(--accent);
        }

        .decision-entry-headline { min-width: 0; }
        .decision-entry-headline strong { font-size: 0.9rem; font-weight: 600; }

        .decision-entry-value {
            margin-top: 2px;
            color: var(--text-2);
            font-size: 0.86rem;
            overflow-wrap: anywhere;
        }

        .decision-entry-meta {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .decision-badge {
            padding: 2px 9px;
            border: 1px solid var(--border);
            border-radius: 999px;
            color: var(--muted);
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            white-space: nowrap;
            text-decoration: none;
        }

        .decision-badge--spec {
            border-color: color-mix(in srgb, var(--accent) 40%, var(--border));
            background: var(--accent-soft);
            color: var(--accent-strong);
        }

        .decision-badge--superseded,
        .decision-badge--changed {
            border-color: color-mix(in srgb, var(--warn-fill) 50%, var(--border));
            background: color-mix(in srgb, var(--warn-fill) 14%, transparent);
            color: var(--warn);
        }

        .decision-entry-panel {
            padding: 0 16px 14px;
            border-top: 1px solid var(--border);
            font-size: 0.87rem;
        }

        .decision-entry:not([open]) .decision-entry-panel { display: none; }

        .decision-detail-grid {
            display: grid;
            gap: 10px;
            margin: 0;
            padding-top: 12px;
        }

        .decision-detail-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 2px;
        }

        @media (min-width: 560px) {
            .decision-detail-row { grid-template-columns: 116px minmax(0, 1fr); gap: 12px; }
        }

        .decision-detail-label {
            padding-top: 2px;
            color: var(--muted);
            font-size: 0.7rem;
            font-weight: 650;
            letter-spacing: 0.07em;
            text-transform: uppercase;
        }

        .decision-detail-value {
            margin: 0;
            overflow-wrap: anywhere;
        }
    </style>
    @endpush
@endonce
