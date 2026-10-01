{{-- The scanners under Security: one tab each. --}}
@php $current = $current ?? 'aikido'; @endphp
<nav class="security-tabs" aria-label="Security scanners">
    <a href="{{ route('larapilot.dashboard.security') }}" @class(['is-current' => $current === 'aikido']) @if ($current === 'aikido') aria-current="page" @endif>Aikido</a>
    <a href="{{ route('larapilot.dashboard.security.checkpoint') }}" @class(['is-current' => $current === 'checkpoint']) @if ($current === 'checkpoint') aria-current="page" @endif>Checkpoint</a>
    <a href="{{ route('larapilot.dashboard.sbom') }}">Dependencies (SBOM)</a>
</nav>
<style>
    .security-tabs { display: flex; gap: 2px; max-width: 100%; padding: 4px; overflow-x: auto; scrollbar-width: none; border: 1px solid var(--border); border-radius: 999px; background: var(--surface-2); align-self: flex-start; }
    .security-tabs::-webkit-scrollbar { display: none; }
    .security-tabs a { display: inline-flex; flex: none; align-items: center; min-height: 34px; padding: 0 12px; border-radius: 999px; color: var(--text-2); font-size: 0.84rem; font-weight: 600; white-space: nowrap; text-decoration: none; }
    .security-tabs a:hover { background: var(--surface-3); color: var(--text); text-decoration: none; }
    .security-tabs a.is-current { background: var(--surface); color: var(--accent-strong); box-shadow: var(--shadow); }
</style>
