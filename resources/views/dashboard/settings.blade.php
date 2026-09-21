@extends('larapilot::dashboard.layout')

@section('title', 'Settings')

@push('styles')
<style>
    body .shell:has(.settings-page) {
        max-width: none;
        padding-left: max(20px, 4vw);
        padding-right: max(20px, 4vw);
    }

    .settings-page {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .settings-panel {
        padding: 24px 28px;
    }

    .settings-panel h2 {
        margin: 0 0 6px;
        font-size: 1.15rem;
    }

    .settings-panel .sub {
        margin: 0 0 24px;
        color: var(--muted);
        font-size: 0.9rem;
        max-width: 72ch;
    }

    .settings-list {
        display: flex;
        flex-direction: column;
        gap: 0;
    }

    .setting-block {
        padding: 20px 0;
        border-top: 1px solid var(--border);
    }

    .setting-block:first-child {
        border-top: 0;
        padding-top: 0;
    }

    .setting-head {
        display: flex;
        align-items: baseline;
        flex-wrap: wrap;
        gap: 8px 12px;
        margin-bottom: 8px;
    }

    .setting-head h3 {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
    }

    .setting-key {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--muted);
        background: color-mix(in srgb, var(--border) 40%, transparent);
        padding: 2px 8px;
        border-radius: 6px;
    }

    .setting-desc {
        margin: 0 0 14px;
        color: var(--muted);
        font-size: 0.875rem;
        line-height: 1.55;
        max-width: 80ch;
    }

    .chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 12px;
    }

    .chip {
        display: inline-flex;
        align-items: center;
        padding: 6px 12px;
        border-radius: 999px;
        border: 1px solid var(--border);
        font-size: 0.82rem;
        color: var(--muted);
        background: color-mix(in srgb, var(--border) 35%, transparent);
    }

    .chip.current {
        border-color: var(--accent);
        background: var(--accent-soft);
        color: var(--accent);
        font-weight: 600;
    }

    .option-guide {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: 6px;
    }

    .option-guide li {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 10px;
        align-items: baseline;
        font-size: 0.82rem;
        color: var(--muted);
        line-height: 1.45;
    }

    .option-guide .option-id {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--text);
        white-space: nowrap;
    }

    .option-guide li.is-current .option-id {
        color: var(--accent);
    }

</style>
@endpush

@section('content')
    @php
        $settingCatalog = [
            'effort' => [
                'label' => 'Effort',
                'description' => 'How deep Larapilot works on each skill — token economy vs thoroughness. ECO also disables Lucille until you re-enable it explicitly.',
                'options' => [
                    'ECO' => 'Save tokens: no sub-agents, lighter docs, skip deep/E2E.',
                    'STANDARD' => 'Normal depth for plans, implement, and review (default).',
                    'MAX' => 'Deep on every flow: sub-agents, richer personas, fuller plans and reviews.',
                ],
            ],
            'backlog' => [
                'label' => 'Backlog granularity',
                'description' => 'How finely Mark slices the product into user stories and epics. Coverage stays the same — only the number of US-XXX files changes.',
                'options' => [
                    'LEAN' => 'Fewest specs: one per end-to-end journey; ≤ 5 epics.',
                    'STANDARD' => 'One spec per user capability (default).',
                    'GRANULAR' => 'Fine-grained: split by seam, admin entity, or locale — for large teams.',
                ],
            ],
            'git_mode' => [
                'label' => 'Git mode',
                'description' => 'Branching and remote discipline for implement and ship. Push/PR updates only with GITFLOW_PUSH.',
                'options' => [
                    'NO_GITFLOW' => 'Stay on the current branch; commits only, no feature-branch ceremony.',
                    'GITFLOW' => 'feature/US-XXX-* branches, atomic commits, PR prepared locally; no auto-push (default).',
                    'GITFLOW_PUSH' => 'Same as GITFLOW, plus push and open/update the PR toward develop after each task.',
                ],
            ],
            'testing' => [
                'label' => 'Testing',
                'description' => 'Anne\'s bar for plan, implement, and review — how deep automated tests must go.',
                'options' => [
                    'MINIMAL' => 'Critical-path Pest/PHPUnit only; no browser or E2E.',
                    'NORMAL' => 'Feature, unit, policy, and API tests plus review evidence; no Playwright/Dusk (default).',
                    'BEST' => 'Full automation: E2E, viewport matrix, axe, Lighthouse when applicable.',
                ],
            ],
            'account' => [
                'label' => 'Account',
                'description' => 'Who is selling the work. FREELANCE and COMPANY unlock the Economics page with country-aware quotes, tax, margins, and SaaS forecasts. Calibrate country and regime with /larapilot-economics.',
                'options' => [
                    'NONE' => 'No Economics section — quotes stay out of scope (default).',
                    'FREELANCE' => 'Partita IVA / sole trader: forfettario, IRPEF, autónomo, sole trader…',
                    'COMPANY' => 'Structured entity: SRL, SPA, Ltd, GmbH, C-Corp…',
                ],
            ],
            'auto_approve' => [
                'label' => 'Auto approve',
                'description' => 'Whether autopilot may mark specs DONE after implement without waiting for your explicit Approve.',
                'options' => [
                    'NO' => 'Always wait for human Approve or Request changes (default).',
                    'YES' => 'After REVIEW, autopilot may spec-approve from a short checklist.',
                ],
            ],
            'lucille' => [
                'label' => 'Project tracking (Lucille)',
                'description' => 'Usage ledger, deadlines, Gantt, and /larapilot-usage reporting on every skill. ON by default; choose NO only to exclude explicitly.',
                'options' => [
                    'YES' => 'Log tokens and hours, track deadlines and epics (default).',
                    'NO' => 'No usage-log or Lucille interview rounds; historical ledger stays readable.',
                ],
            ],
            'decision_log' => [
                'label' => 'Decision journal',
                'description' => 'Records explicit choices in .larapilot/decisions.yaml and runs decision-check before contradicting an earlier answer.',
                'options' => [
                    'YES' => 'Journal AskQuestion answers; flag contradictions before superseding (default).',
                    'NO' => 'Do not record decisions or run the regression guard.',
                ],
            ],
            'code_history' => [
                'label' => 'Code change history',
                'description' => 'Per spec/task log of files and line ranges touched, derived from the task git commit into .larapilot/code-history.yaml.',
                'options' => [
                    'YES' => 'After each task-done, append touched files and line ranges from the commit.',
                    'NO' => 'No code change history (default).',
                ],
            ],
            'release_mode' => [
                'label' => 'Release mode',
                'description' => 'Semver release ledger in .larapilot/releases.yaml with /larapilot-release and optional Gitflow release/x.y.z branches.',
                'options' => [
                    'YES' => 'Release ledger, ship ceremony, and parallel release branches when Gitflow is active.',
                    'NO' => 'Classic develop/feature flow only (default).',
                ],
            ],
            'project_docs' => [
                'label' => 'Project docs',
                'description' => 'Living technical and functional handbook under _project_docs/, maintained incrementally when material changes land.',
                'options' => [
                    'YES' => 'Albert maintains chapters and diagrams; bootstrap from history if enabled mid-project.',
                    'NO' => 'No handbook obligation (default).',
                ],
            ],
            'comments' => [
                'label' => 'Comments',
                'description' => 'Internal PM/dev feedback on dashboard specs and via the API until the spec is DONE. Stored in .larapilot/internal-feedback/.',
                'options' => [
                    'YES' => 'Dashboard UI, API, and larapilot:spec-comment enabled.',
                    'NO' => 'Hide feedback UI and reject new comments project-wide (default).',
                ],
            ],
            'dashboard_auth' => [
                'label' => 'Dashboard auth',
                'description' => 'HTTP Basic Auth on the /larapilot dashboard UI only. Credentials live in .larapilot/auth.yaml (git-ignored).',
                'options' => [
                    'NO' => 'Dashboard open in allowed environments (default).',
                    'YES' => 'Require username + password; manage users via larapilot:dashboard-user.',
                ],
            ],
            'api_auth' => [
                'label' => 'API auth',
                'description' => 'Makes LARAPILOT_API_TOKEN mandatory on every /larapilot/api/* request. Never affects the dashboard UI or MCP.',
                'options' => [
                    'NO' => 'Token enforced when set; reads open in dev/staging when unset (default).',
                    'YES' => 'Every API call needs the token; fails closed (HTTP 503) when LARAPILOT_API_TOKEN is missing.',
                ],
            ],
            'security_scan' => [
                'label' => 'Security scan',
                'description' => 'Runs andreapollastri/checkpoint during /larapilot-review and the pre-ship gate. Requires composer require --dev andreapollastri/checkpoint.',
                'options' => [
                    'NO' => 'No security scan step (default).',
                    'YES' => 'checkpoint:scan in review; FAIL = blocker, WARN = review note.',
                ],
            ],
            'github' => [
                'label' => 'GitHub',
                'description' => 'Use gh CLI for remote pull requests. Orthogonal to git_mode; OFF by default.',
                'options' => [
                    'YES' => 'gh pr create/view; print PR URL; notify pr_* events.',
                    'NO' => 'No GitHub CLI integration (default).',
                ],
            ],
            'gitlab' => [
                'label' => 'GitLab',
                'description' => 'Use glab CLI for merge requests. OFF by default.',
                'options' => [
                    'YES' => 'glab mr create/view; print MR URL; notify pr_* events.',
                    'NO' => 'No GitLab CLI integration (default).',
                ],
            ],
            'bitbucket' => [
                'label' => 'Bitbucket',
                'description' => 'Bitbucket Cloud REST API with access token or app password for PRs. OFF by default.',
                'options' => [
                    'YES' => 'Create/view PRs via API; print PR URL; notify pr_* events.',
                    'NO' => 'No Bitbucket integration (default).',
                ],
            ],
            'azure' => [
                'label' => 'Azure DevOps',
                'description' => 'az CLI or PAT for Azure Repos pull requests. OFF by default.',
                'options' => [
                    'YES' => 'az repos pr create/show; print PR URL; notify pr_* events.',
                    'NO' => 'No Azure DevOps integration (default).',
                ],
            ],
            'notifications' => [
                'label' => 'Notifications',
                'description' => 'Master switch for chat alerts (Slack, Discord, Telegram). Channel secrets stay in .env.',
                'options' => [
                    'NO' => 'No chat fan-out (default).',
                    'YES' => 'Enable notifications; configure individual channels below.',
                ],
            ],
            'notify_slack' => [
                'label' => 'Notify Slack',
                'description' => 'Incoming webhook alerts when notifications are ON. Set LARAPILOT_SLACK_WEBHOOK_URL in .env.',
                'options' => [
                    'YES' => 'Send events to Slack via incoming webhook.',
                    'NO' => 'Slack channel disabled (default).',
                ],
            ],
            'notify_discord' => [
                'label' => 'Notify Discord',
                'description' => 'Channel webhook alerts when notifications are ON. Set LARAPILOT_DISCORD_WEBHOOK_URL in .env.',
                'options' => [
                    'YES' => 'Send events to Discord via channel webhook.',
                    'NO' => 'Discord channel disabled (default).',
                ],
            ],
            'notify_telegram' => [
                'label' => 'Notify Telegram',
                'description' => 'Bot alerts when notifications are ON. Set LARAPILOT_TELEGRAM_BOT_TOKEN and LARAPILOT_TELEGRAM_CHAT_ID in .env.',
                'options' => [
                    'YES' => 'Send events via BotFather token + chat_id.',
                    'NO' => 'Telegram channel disabled (default).',
                ],
            ],
        ];
    @endphp

    <div class="settings-page">
        <section class="card settings-panel">
            <h2>Project settings</h2>
            <p class="sub">Read-only snapshot of <code>.larapilot/config.yaml</code>. Change values with <code>/larapilot-settings</code> or <code>php artisan larapilot:settings-set</code>.</p>

            <div class="settings-list">
                @foreach (($settings['options'] ?? []) as $key => $options)
                    @php
                        $meta = $settingCatalog[$key] ?? [
                            'label' => str_replace('_', ' ', $key),
                            'description' => null,
                            'options' => [],
                        ];
                        $current = $settings['current'][$key] ?? null;
                        $currentNorm = is_bool($current) ? ($current ? 'YES' : 'NO') : $current;
                        $optionHints = $meta['options'] ?? [];
                    @endphp
                    <article class="setting-block">
                        <div class="setting-head">
                            <h3>{{ $meta['label'] }}</h3>
                            <code class="setting-key">{{ $key }}</code>
                        </div>

                        @if (! empty($meta['description']))
                            <p class="setting-desc">{{ $meta['description'] }}</p>
                        @endif

                        <div class="chips">
                            @foreach ($options as $option)
                                <span @class(['chip', 'current' => (string) $currentNorm === (string) $option])>{{ $option }}</span>
                            @endforeach
                        </div>

                        @if ($optionHints !== [])
                            <ul class="option-guide">
                                @foreach ($options as $option)
                                    @if (! empty($optionHints[$option]))
                                        <li @class(['is-current' => (string) $currentNorm === (string) $option])>
                                            <span class="option-id">{{ $option }}</span>
                                            <span>{{ $optionHints[$option] }}</span>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    </div>
@endsection
