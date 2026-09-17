@php
    $settingLabels = [
        'effort' => 'Effort',
        'backlog' => 'Backlog',
        'testing' => 'Testing',
        'git_mode' => 'Git mode',
        'lucille' => 'Project tracking',
        'auto_approve' => 'Auto approve',
        'decision_log' => 'Decision log',
        'code_history' => 'Code history',
        'comments' => 'Comments',
        'dashboard_auth' => 'Dashboard auth',
        'api_auth' => 'API auth',
        'security_scan' => 'Security scan',
        'github' => 'GitHub',
        'gitlab' => 'GitLab',
        'bitbucket' => 'Bitbucket',
        'azure' => 'Azure DevOps',
        'notifications' => 'Notifications',
        'notify_slack' => 'Notify Slack',
        'notify_discord' => 'Notify Discord',
        'notify_telegram' => 'Notify Telegram',
    ];
@endphp

<section class="card project-settings-summary">
    <div class="project-settings-header">
        <div>
            <h2>Project settings</h2>
            <p class="sub">Current values from <code>.larapilot/config.yaml</code> — change via <code>/larapilot-settings</code>.</p>
        </div>
        <a class="project-settings-link" href="{{ route('larapilot.dashboard.settings') }}">View all →</a>
    </div>

    <div class="project-settings-chips">
        @foreach (($settings['options'] ?? []) as $key => $options)
            @php
                $current = $settings['current'][$key] ?? null;
                $currentNorm = is_bool($current) ? ($current ? 'YES' : 'NO') : $current;
                $label = $settingLabels[$key] ?? str_replace('_', ' ', $key);
            @endphp
            <span class="setting-pill" title="{{ $label }}: {{ $currentNorm }}">
                <span class="setting-pill-label">{{ $label }}</span>
                <strong>{{ $currentNorm }}</strong>
            </span>
        @endforeach
    </div>
</section>
