{{--
    The version scope: one chip per version of the ledger and one for the
    stories no version claims. A click adds or removes that version; the
    scope stays with the person on the Board, the Plan, the Forecast, and
    the Versions page until "All versions" clears it.

    $scope: ['versions' => rows of VersionService, 'selected' => list<string>]
    $scopeRoute: the route the chips link to
    $scopeParams: the other query parameters the page keeps (group, …)
--}}
@php
    $scopeSelected = $scope['selected'] ?? [];
    $scopeParams = $scopeParams ?? [];
    $scopeLink = static function (array $selection) use ($scopeRoute, $scopeParams): string {
        return route($scopeRoute, $scopeParams + ['version' => $selection === [] ? 'all' : implode(',', $selection)]);
    };
    $scopeToggle = static function (string $value) use ($scopeSelected): array {
        return in_array($value, $scopeSelected, true)
            ? array_values(array_diff($scopeSelected, [$value]))
            : [...$scopeSelected, $value];
    };
    $scopeStateLabel = ['shipped' => 'Shipped', 'in_progress' => 'In progress', 'planned' => 'Planned'];
@endphp
@once
    <style>
        .version-scope { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 10px; margin: 0 0 16px; }
        .version-scope-label { font-size: 0.72rem; font-weight: 650; letter-spacing: 0.05em; text-transform: uppercase; color: var(--muted); }
        .version-scope .chips { gap: 6px; }
        .version-scope a.chip { text-decoration: none; }
        .version-scope a.chip:hover { border-color: var(--border-strong); color: var(--text); }
        .version-scope a.chip.current:hover { color: var(--accent-strong); }
        .version-scope .dot { width: 7px; height: 7px; border-radius: 999px; background: var(--border-strong); flex-shrink: 0; }
        .version-scope .dot.shipped { background: var(--ok-fill); }
        .version-scope .dot.in_progress { background: var(--accent); }
        .version-scope .dot.none { background: transparent; border: 1px dashed var(--muted); }
        .version-scope .pct { font-variant-numeric: tabular-nums; opacity: 0.8; font-size: 0.72rem; }
        .version-scope-open { font-size: 0.8rem; }
    </style>
@endonce
<nav class="version-scope" aria-label="Version scope">
    <span class="version-scope-label">Versions</span>
    <div class="chips">
        <a href="{{ $scopeLink([]) }}" @class(['chip', 'current' => $scopeSelected === []]) @if ($scopeSelected === []) aria-current="true" @endif>All versions</a>
        @foreach ($scope['versions'] ?? [] as $scopeRow)
            @php $scopeOn = in_array($scopeRow['version'], $scopeSelected, true); @endphp
            <a href="{{ $scopeLink($scopeToggle($scopeRow['version'])) }}"
               @class(['chip', 'current' => $scopeOn])
               @if ($scopeOn) aria-current="true" @endif
               title="{{ trim(($scopeRow['title'] !== '' ? $scopeRow['title'].' — ' : '').($scopeStateLabel[$scopeRow['status']] ?? $scopeRow['status']).', '.$scopeRow['progress'].'% delivered') }}">
                <span @class(['dot', $scopeRow['status']]) aria-hidden="true"></span>v{{ $scopeRow['version'] }}
                <span class="pct">{{ $scopeRow['progress'] }}%</span>
            </a>
        @endforeach
        @php $scopeNone = in_array(\Larapilot\Services\VersionService::NONE, $scopeSelected, true); @endphp
        <a href="{{ $scopeLink($scopeToggle(\Larapilot\Services\VersionService::NONE)) }}" @class(['chip', 'current' => $scopeNone]) @if ($scopeNone) aria-current="true" @endif title="Stories no version claims">
            <span class="dot none" aria-hidden="true"></span>No version
        </a>
    </div>
    @if (count($scopeSelected) === 1 && $scopeSelected[0] !== \Larapilot\Services\VersionService::NONE)
        <a class="version-scope-open" href="{{ route('larapilot.dashboard.versions.show', ['version' => $scopeSelected[0]]) }}">Open v{{ $scopeSelected[0] }} →</a>
    @endif
</nav>
