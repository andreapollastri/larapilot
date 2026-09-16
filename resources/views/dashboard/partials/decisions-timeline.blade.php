<div class="decisions-timeline" data-exclusive-accordion>
    @foreach ($entries as $entry)
        <details @class([
            'decision-entry',
            'decision-entry--superseded' => ! empty($entry['is_superseded']),
        ])>
            <summary class="decision-entry-summary" aria-label="Toggle decision {{ $entry['label'] ?? '' }}">
                <div class="decision-entry-title">
                    <svg class="decision-entry-chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                    </svg>
                    <div class="decision-entry-headline">
                        <strong>{{ $entry['label'] ?? $entry['topic'] ?? 'Decision' }}</strong>
                        <div class="decision-entry-value">{{ $entry['value'] ?? '' }}</div>
                    </div>
                </div>
                <div class="decision-entry-meta">
                    @if (! empty($entry['at']))
                        <span class="decision-badge">{{ $entry['at'] }}</span>
                    @endif
                    @if (! empty($entry['spec']) && empty($hide_spec_badge ?? false))
                        <a class="decision-badge decision-badge--spec" href="{{ route('larapilot.dashboard.spec', $entry['spec']) }}">{{ $entry['spec'] }}</a>
                    @endif
                    @if (! empty($entry['is_superseded']))
                        <span class="decision-badge decision-badge--superseded">Superseded</span>
                    @elseif (! empty($entry['supersedes']))
                        <span class="decision-badge decision-badge--changed">Reversal</span>
                    @endif
                </div>
            </summary>
            <div class="decision-entry-panel">
                <dl class="decision-detail-grid">
                    @if (! empty($entry['question']))
                        <div class="decision-detail-row">
                            <dt class="decision-detail-label">Question</dt>
                            <dd class="decision-detail-value">{{ $entry['question'] }}</dd>
                        </div>
                    @endif
                    @if (! empty($entry['rationale']))
                        <div class="decision-detail-row">
                            <dt class="decision-detail-label">Rationale</dt>
                            <dd class="decision-detail-value">{{ $entry['rationale'] }}</dd>
                        </div>
                    @endif
                    @if (! empty($entry['phase']))
                        <div class="decision-detail-row">
                            <dt class="decision-detail-label">Phase</dt>
                            <dd class="decision-detail-value"><code>{{ $entry['phase'] }}</code></dd>
                        </div>
                    @endif
                    @if (! empty($entry['source']))
                        <div class="decision-detail-row">
                            <dt class="decision-detail-label">Source</dt>
                            <dd class="decision-detail-value">{{ $entry['source'] }}</dd>
                        </div>
                    @endif
                    @if (! empty($entry['user']))
                        <div class="decision-detail-row">
                            <dt class="decision-detail-label">Recorded by</dt>
                            <dd class="decision-detail-value">{{ $entry['user'] }}</dd>
                        </div>
                    @endif
                    @if (! empty($entry['supersedes']))
                        <div class="decision-detail-row">
                            <dt class="decision-detail-label">Supersedes</dt>
                            <dd class="decision-detail-value"><code>{{ $entry['supersedes'] }}</code></dd>
                        </div>
                    @endif
                </dl>
            </div>
        </details>
    @endforeach
</div>
