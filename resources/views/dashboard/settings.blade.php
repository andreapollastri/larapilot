@extends('larapilot::dashboard.layout')

@section('title', 'Settings')

@push('styles')
<style>
    .settings-tools {
        display: flex;
        flex-wrap: wrap;
        align-items: end;
        gap: 12px 16px;
        margin-bottom: 22px;
    }

    .settings-tools .field { flex: 1 1 240px; max-width: 380px; }

    .settings-jump {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        flex: 2 1 320px;
    }

    .settings-jump a {
        padding: 5px 12px;
        border: 1px solid var(--border);
        border-radius: 999px;
        background: var(--surface);
        color: var(--text-2);
        font-size: 0.8rem;
        font-weight: 550;
        text-decoration: none;
    }

    .settings-jump a:hover { border-color: var(--accent); color: var(--accent); }

    .settings-group { margin-bottom: 26px; scroll-margin-top: 80px; }

    .settings-group > h3 {
        margin: 0 0 10px;
        color: var(--muted);
        font-size: 0.72rem;
        font-weight: 650;
        letter-spacing: 0.09em;
        text-transform: uppercase;
    }

    .settings-list { overflow: hidden; }

    .setting-block {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 14px;
        padding: 18px;
        border-top: 1px solid var(--border);
    }

    .setting-block:first-child { border-top: 0; }

    @media (min-width: 900px) {
        .setting-block {
            grid-template-columns: minmax(0, 5fr) minmax(0, 6fr);
            gap: 28px;
            padding: 22px 24px;
        }
    }

    .setting-head {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px 10px;
        margin-bottom: 6px;
    }

    .setting-head h4 {
        margin: 0;
        font-size: 0.98rem;
    }

    .setting-key {
        padding: 2px 7px;
        border-radius: 5px;
        background: var(--surface-3);
        color: var(--muted);
        font-size: 0.72rem;
        font-weight: 600;
    }

    .setting-desc {
        margin: 0;
        color: var(--muted);
        font-size: 0.86rem;
        line-height: 1.55;
        max-width: 70ch;
    }

    .option-guide {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: 6px;
        align-content: start;
    }

    .option-guide li {
        display: grid;
        grid-template-columns: 16px minmax(0, 1fr);
        gap: 10px;
        align-items: start;
        padding: 9px 12px;
        border: 1px solid transparent;
        border-radius: var(--radius-sm);
        color: var(--muted);
        font-size: 0.83rem;
        line-height: 1.45;
    }

    .option-guide li::before {
        content: '';
        width: 14px;
        height: 14px;
        margin-top: 3px;
        border: 1.5px solid var(--border-strong);
        border-radius: 999px;
    }

    .option-guide .option-id {
        display: block;
        color: var(--text-2);
        font-family: var(--mono);
        font-size: 0.74rem;
        font-weight: 600;
        letter-spacing: 0.02em;
    }

    .option-guide li.is-current {
        border-color: color-mix(in srgb, var(--accent) 35%, var(--border));
        background: var(--accent-soft);
        color: var(--text-2);
    }

    .option-guide li.is-current::before {
        border-color: var(--accent);
        background: radial-gradient(circle, var(--accent) 0 4px, transparent 4.5px);
    }

    .option-guide li.is-current .option-id { color: var(--accent-strong); }

    .option-current {
        display: inline-block;
        margin-left: 6px;
        color: var(--accent);
        font-family: var(--font);
        font-size: 0.66rem;
        font-weight: 650;
        letter-spacing: 0.07em;
        text-transform: uppercase;
    }

    .settings-none {
        margin: 0;
        padding: 28px 18px;
        text-align: center;
        color: var(--muted);
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
            'prior_art' => [
                'label' => 'Prior art check',
                'description' => 'At inception Sebastian searches GitHub, Packagist, and OSS catalogs for existing solutions before scope is written; the verdict lands in the PRD and .larapilot/research/prior-art.md.',
                'options' => [
                    'YES' => 'Ask consent for the queries, search, and record Build anyway / Adopt / Integrate (default).',
                    'NO' => 'Skip the round; the PRD records Prior Art: Not checked.',
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

    @php
        $settingGroups = [
            'Delivery' => ['effort', 'backlog', 'git_mode', 'testing', 'auto_approve'],
            'Tracking and documentation' => ['lucille', 'decision_log', 'prior_art', 'code_history', 'release_mode', 'project_docs', 'comments'],
            'Business' => ['account'],
            'Security' => ['dashboard_auth', 'api_auth', 'security_scan'],
            'Forges' => ['github', 'gitlab', 'bitbucket', 'azure'],
            'Notifications' => ['notifications', 'notify_slack', 'notify_discord', 'notify_telegram'],
        ];

        $available = is_array($settings['options'] ?? null) ? $settings['options'] : [];
        $sections = [];
        $placed = [];

        foreach ($settingGroups as $groupName => $groupKeys) {
            foreach ($groupKeys as $groupKey) {
                if (array_key_exists($groupKey, $available)) {
                    $sections[$groupName][$groupKey] = $available[$groupKey];
                    $placed[$groupKey] = true;
                }
            }
        }

        // A setting the package gained after this page was last touched still shows.
        foreach ($available as $availableKey => $availableOptions) {
            if (! isset($placed[$availableKey])) {
                $sections['Other'][$availableKey] = $availableOptions;
            }
        }
    @endphp

    <div class="settings-page">
        <header class="page-head">
            <div>
                <h2>Project settings</h2>
                <p class="sub">Read-only snapshot of <code>.larapilot/config.yaml</code>. Change values with <code>/larapilot-settings</code> or <code>php artisan larapilot:settings-set</code>.</p>
            </div>
        </header>

        <div class="settings-tools">
            <label class="field">
                Find a setting
                <input type="search" id="settings-q" placeholder="Name, key, or what it does…" autocomplete="off">
            </label>
            <nav class="settings-jump" aria-label="Setting groups">
                @foreach (array_keys($sections) as $sectionName)
                    <a href="#settings-{{ \Illuminate\Support\Str::slug($sectionName) }}">{{ $sectionName }}</a>
                @endforeach
            </nav>
        </div>

        @foreach ($sections as $sectionName => $sectionSettings)
            <section class="settings-group" id="settings-{{ \Illuminate\Support\Str::slug($sectionName) }}" data-settings-group>
                <h3>{{ $sectionName }}</h3>
                <div class="card settings-list">
                    @foreach ($sectionSettings as $key => $options)
                        @php
                            $meta = $settingCatalog[$key] ?? [
                                'label' => str_replace('_', ' ', $key),
                                'description' => null,
                                'options' => [],
                            ];
                            $current = $settings['current'][$key] ?? null;
                            $currentNorm = is_bool($current) ? ($current ? 'YES' : 'NO') : $current;
                            $optionHints = $meta['options'] ?? [];
                            $settingSearch = strtolower(implode(' ', [$meta['label'], $key, (string) ($meta['description'] ?? ''), (string) $currentNorm]));
                        @endphp
                        <article class="setting-block" data-setting data-search="{{ $settingSearch }}">
                            <div>
                                <div class="setting-head">
                                    <h4>{{ $meta['label'] }}</h4>
                                    <code class="setting-key">{{ $key }}</code>
                                </div>

                                @if (! empty($meta['description']))
                                    <p class="setting-desc">{{ $meta['description'] }}</p>
                                @endif
                            </div>

                            <ul class="option-guide" aria-label="Options for {{ $meta['label'] }}">
                                @foreach ($options as $option)
                                    @php $isCurrent = (string) $currentNorm === (string) $option; @endphp
                                    <li @class(['is-current' => $isCurrent])>
                                        <span>
                                            <span class="option-id">{{ $option }}@if ($isCurrent)<span class="option-current">Current</span>@endif</span>
                                            @if (! empty($optionHints[$option]))
                                                {{ $optionHints[$option] }}
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach

        <p class="card settings-none" id="settings-none" hidden>No setting matches that search.</p>
    </div>
@endsection

@push('scripts')
<script>
    (() => {
        const query = document.getElementById('settings-q');
        const none = document.getElementById('settings-none');

        if (!query) {
            return;
        }

        const groups = [...document.querySelectorAll('[data-settings-group]')];

        const apply = () => {
            const needle = query.value.trim().toLowerCase();
            let shown = 0;

            groups.forEach((group) => {
                let visible = 0;

                group.querySelectorAll('[data-setting]').forEach((setting) => {
                    const match = needle === '' || (setting.dataset.search || '').includes(needle);
                    setting.hidden = !match;

                    if (match) {
                        visible += 1;
                    }
                });

                group.hidden = visible === 0;
                shown += visible;
            });

            if (none) {
                none.hidden = shown > 0;
            }
        };

        query.addEventListener('input', apply);
    })();
</script>
@endpush
