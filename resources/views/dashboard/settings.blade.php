@extends('larapilot::dashboard.layout')

@section('title', 'Settings')

@push('styles')
<style>
    .settings-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        align-items: start;
    }

    @media (max-width: 900px) {
        .settings-grid { grid-template-columns: 1fr; }
    }

    .panel { padding: 20px 22px; }

    .panel h2 {
        margin: 0 0 6px;
        font-size: 1.05rem;
    }

    .panel .sub {
        margin: 0 0 18px;
        color: var(--muted);
        font-size: 0.875rem;
    }

    .setting-row {
        display: grid;
        grid-template-columns: 140px 1fr;
        gap: 12px;
        padding: 12px 0;
        border-top: 1px solid var(--border);
        align-items: start;
    }

    .setting-row:first-of-type { border-top: 0; }

    .setting-key {
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--muted);
        padding-top: 4px;
    }

    .chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .chip {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 999px;
        border: 1px solid var(--border);
        font-size: 0.8rem;
        color: var(--muted);
        background: color-mix(in srgb, var(--border) 35%, transparent);
    }

    .chip.current {
        border-color: var(--accent);
        background: var(--accent-soft);
        color: var(--accent);
        font-weight: 600;
    }

    .choice-list {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .choice-list li {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        padding: 10px 0;
        border-top: 1px solid var(--border);
        font-size: 0.9rem;
    }

    .choice-list li:first-child { border-top: 0; }

    .choice-list .label { color: var(--muted); }
    .choice-list .value { font-weight: 600; text-align: right; }

    .path-note {
        margin-top: 16px;
        font-size: 0.8rem;
        color: var(--muted);
    }
</style>
@endpush

@section('content')
    <div class="settings-grid">
        <section class="card panel">
            <h2>Project settings</h2>
            <p class="sub">Current values and allowed options from <code>.larapilot/config.yaml</code> (<code>/larapilot-settings</code>).</p>

            @php
                $settingLabels = [
                    'lucille' => 'Project tracking',
                    'auto_approve' => 'Auto approve',
                    'dashboard_auth' => 'Dashboard auth',
                    'api_auth' => 'API auth',
                    'security_scan' => 'Security scan',
                    'git_mode' => 'Git mode',
                    'notify_slack' => 'Notify Slack',
                    'notify_discord' => 'Notify Discord',
                    'notify_telegram' => 'Notify Telegram',
                ];
            @endphp
            @foreach (($settings['options'] ?? []) as $key => $options)
                <div class="setting-row">
                    <div class="setting-key">{{ $settingLabels[$key] ?? str_replace('_', ' ', $key) }}</div>
                    <div class="chips">
                        @php
                            $current = $settings['current'][$key] ?? null;
                            $currentNorm = is_bool($current) ? ($current ? 'YES' : 'NO') : $current;
                        @endphp
                        @foreach ($options as $option)
                            <span @class(['chip', 'current' => (string) $currentNorm === (string) $option])>{{ $option }}</span>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </section>

        <section class="card panel">
            <h2>Inception choices</h2>
            <p class="sub">Visual summary of decisions recorded during discovery and beyond.</p>

            @if (($inception ?? []) === [])
                <div class="empty" style="padding: 24px;">No choices yet. Run inception, then <code>larapilot:choices-set --from-prd</code>.</div>
            @else
                <ul class="choice-list">
                    @foreach ($inception as $label => $value)
                        <li>
                            <span class="label">{{ $label }}</span>
                            <span class="value">{{ is_array($value) ? json_encode($value) : $value }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <p class="path-note">Snapshot: <code>{{ $path ?? '.larapilot/choices.yaml' }}</code></p>
        </section>
    </div>
@endsection
