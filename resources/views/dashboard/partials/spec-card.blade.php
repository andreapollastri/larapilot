<a @class(['spec-card', 'spec-card-extra' => ($extra ?? false)]) href="{{ route('larapilot.dashboard.spec', $spec['code']) }}">
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
        <div class="spec-indicators">
            <span class="spec-indicator spec-indicator--comments" title="{{ $spec['feedback']['entry_count'] }} comment{{ $spec['feedback']['entry_count'] === 1 ? '' : 's' }}">
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9H7v2h2V9z" clip-rule="evenodd" />
                </svg>
                {{ $spec['feedback']['entry_count'] }}
            </span>
            @if (! empty($spec['feedback']['blocking_count']))
                <span class="spec-indicator spec-indicator--blocking" title="{{ $spec['feedback']['blocking_count'] }} blocking comment{{ $spec['feedback']['blocking_count'] === 1 ? '' : 's' }}">
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd" />
                    </svg>
                    {{ $spec['feedback']['blocking_count'] }}
                </span>
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
