@extends('larapilot::dashboard.layout')

@section('title', 'Docs')

@push('styles')
<style>
    .docs-page {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    .docs-panel { padding: 20px 18px; }

    @media (min-width: 640px) {
        .docs-panel { padding: 26px 28px; }
    }

    .docs-panel h2 {
        margin: 0 0 6px;
        font-size: 1.15rem;
    }

    .docs-panel h3 {
        margin: 26px 0 10px;
        color: var(--muted);
        font-size: 0.72rem;
        font-weight: 650;
        letter-spacing: 0.09em;
        text-transform: uppercase;
    }

    .docs-panel .sub { margin: 0 0 18px; max-width: 80ch; }

    .flow-steps {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 210px), 1fr));
        gap: 10px;
        margin: 0;
        counter-reset: step;
    }

    .flow-step {
        padding: 14px 16px;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--surface-2);
    }

    .flow-step strong {
        display: block;
        margin-bottom: 4px;
        font-size: 0.88rem;
        font-weight: 600;
    }

    .flow-steps.is-ordered .flow-step strong::before {
        counter-increment: step;
        content: counter(step, decimal-leading-zero);
        margin-right: 8px;
        color: var(--accent);
        font-family: var(--mono);
        font-size: 0.72rem;
    }

    .flow-step span {
        color: var(--muted);
        font-size: 0.8rem;
        line-height: 1.5;
    }

    .flow-step code { font-size: 0.74rem; }

    .branch-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: 8px;
    }

    .branch-list li {
        padding: 12px 14px;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        color: var(--text-2);
        font-size: 0.875rem;
        line-height: 1.5;
    }

    .branch-list li strong { color: var(--text); font-weight: 600; }

    .branch-list li.is-on {
        border-color: color-mix(in srgb, var(--accent) 40%, var(--border));
        background: var(--accent-soft);
    }

    .branch-list li.is-on::after {
        content: 'Active';
        float: right;
        margin-left: 10px;
        color: var(--accent);
        font-size: 0.66rem;
        font-weight: 650;
        letter-spacing: 0.07em;
        text-transform: uppercase;
    }

    .branch-list code { font-size: 0.78rem; }

    .skills-table,
    .personas-table {
        width: 100%;
        min-width: 620px;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .personas-table { min-width: 460px; }

    .skills-table th,
    .skills-table td,
    .personas-table th,
    .personas-table td {
        padding: 11px 12px;
        border-top: 1px solid var(--border);
        text-align: left;
        vertical-align: top;
    }

    .skills-table th,
    .personas-table th {
        border-top: 0;
        color: var(--muted);
        font-size: 0.7rem;
        font-weight: 650;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .skills-table td:first-child,
    .personas-table td:first-child { white-space: nowrap; }

    .skills-table code,
    .personas-table code { font-size: 0.78rem; }

    .skill-optional {
        display: inline-block;
        margin-left: 4px;
        color: var(--muted);
        font-size: 0.74rem;
    }

    .folder-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 230px), 1fr));
        gap: 10px;
    }

    .folder-list a {
        display: block;
        padding: 14px 16px;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--surface-2);
        color: inherit;
        text-decoration: none;
    }

    .folder-list a:hover { border-color: var(--accent); }
    .folder-list code { font-size: 0.78rem; }

    .folder-list span {
        display: block;
        margin-top: 6px;
        color: var(--muted);
        font-size: 0.8rem;
        line-height: 1.45;
    }
</style>
@endpush

@section('content')
    @php
        $s = $settings ?? [];
        $isYes = static fn (mixed $value): bool => $value === 'YES' || $value === true;
    @endphp

    <div class="docs-page">
        <header class="page-head" style="margin-bottom: 4px;">
            <div>
                <h2>How Larapilot works</h2>
                <p class="sub">Larapilot turns your AI agent into a spec-driven product squad. Boost skills orchestrate the conversation; <code>php artisan larapilot:*</code> persists artifacts; <code>.larapilot/</code> in the repo is the source of truth between sessions.</p>
            </div>
        </header>

        <section class="card docs-panel">
            <h3 style="margin-top: 0;">Core delivery loop</h3>
            <div class="flow-steps is-ordered">
                <div class="flow-step"><strong>Discovery</strong><span><code>/larapilot-inception</code> or <code>/larapilot-adopt</code> → PRD</span></div>
                <div class="flow-step"><strong>Backlog</strong><span><code>/larapilot-spec</code> → user stories</span></div>
                <div class="flow-step"><strong>Plan</strong><span><code>/larapilot-plan</code> → tasks + tests</span></div>
                <div class="flow-step"><strong>Design</strong><span><code>/larapilot-design</code> → mockups — gallery at <a href="{{ route('larapilot.dashboard.design') }}">/larapilot/design</a></span></div>
                <div class="flow-step"><strong>Implement</strong><span><code>/larapilot-implement</code> → code + commits</span></div>
                <div class="flow-step"><strong>Review</strong><span><code>/larapilot-review</code> → DONE or rework</span></div>
                <div class="flow-step"><strong>Ship</strong><span><code>/larapilot-ship</code> → deploy (optional)</span></div>
            </div>

            <h3>Side paths</h3>
            <div class="flow-steps">
                <div class="flow-step"><strong>Feature</strong><span><code>/larapilot-feature</code> — new enhancement on brownfield</span></div>
                <div class="flow-step"><strong>Bug</strong><span><code>/larapilot-bug</code> — triage + fix spec</span></div>
                <div class="flow-step"><strong>Triage</strong><span><code>/larapilot-triage</code> — bug or feature? classifies the request and hands off</span></div>
                <div class="flow-step"><strong>Aikido</strong><span><code>/larapilot-aikido</code> — downloads the security findings and hands each one to triage</span></div>
                <div class="flow-step"><strong>Boogle</strong><span><code>/larapilot-boogle</code> — downloads the errors thrown in production and hands each bug to triage</span></div>
                <div class="flow-step"><strong>PRD revision</strong><span><code>/larapilot-prd</code> — change the PRD when it is neither: sharpen, re-scope, re-decide, upgrade</span></div>
                <div class="flow-step"><strong>Autopilot</strong><span><code>/larapilot-autopilot</code> — one spec at a time; plan and implement in a fresh worker when effort is not ECO</span></div>
                <div class="flow-step"><strong>Settings</strong><span><code>/larapilot-settings</code> → <code>config.yaml</code></span></div>
            </div>
        </section>

        <section class="card docs-panel">
            <h2>Flow branches by project settings</h2>
            <p class="sub">Every skill reads <code>data.settings</code> from <code>larapilot:config-show</code> before acting. Highlights below reflect your current <code>.larapilot/config.yaml</code> (ON settings marked).</p>

            <ul class="branch-list">
                <li @class(['is-on' => ($s['effort'] ?? 'STANDARD') === 'ECO'])>
                    <strong>Effort = ECO</strong> — save tokens: no sub-agents, Lucille disabled automatically, lighter docs, skip deep/E2E. Re-enable Lucille with <code>--lucille=YES</code> without leaving ECO.
                </li>
                <li @class(['is-on' => ($s['effort'] ?? 'STANDARD') === 'MAX'])>
                    <strong>Effort = MAX</strong> — deep mode on every flow: richer persona rounds, optional sub-agents, expanded plans and reviews.
                </li>
                <li @class(['is-on' => ($s['backlog'] ?? 'STANDARD') === 'LEAN'])>
                    <strong>Backlog = LEAN</strong> — fewest specs: one per end-to-end journey; technical seams stay plan tasks.
                </li>
                <li @class(['is-on' => ($s['backlog'] ?? 'STANDARD') === 'GRANULAR'])>
                    <strong>Backlog = GRANULAR</strong> — fine-grained specs for parallel teams or one-PR-per-spec workflows.
                </li>
                <li @class(['is-on' => ($s['git_mode'] ?? 'GITFLOW') === 'GITFLOW_PUSH'])>
                    <strong>Git mode = GITFLOW_PUSH</strong> — push and open/update PRs toward develop after each task (only this mode auto-pushes).
                </li>
                <li @class(['is-on' => ($s['git_mode'] ?? 'GITFLOW') === 'NO_GITFLOW'])>
                    <strong>Git mode = NO_GITFLOW</strong> — stay on current branch; commits only, no feature-branch ceremony.
                </li>
                <li @class(['is-on' => ($s['testing'] ?? 'NORMAL') === 'BEST'])>
                    <strong>Testing = BEST</strong> — E2E (Playwright/Dusk), viewport matrix, axe, Lighthouse when applicable.
                </li>
                <li @class(['is-on' => in_array($s['account'] ?? 'NONE', ['FREELANCE', 'COMPANY'], true)])>
                    <strong>Account = {{ $s['account'] ?? 'NONE' }}</strong> — Economics quotes on <a href="{{ route('larapilot.dashboard.economics') }}">/larapilot/economics</a> (freelance or company tax). Calibrate with <code>/larapilot-economics</code>.
                </li>
                <li @class(['is-on' => $isYes($s['auto_approve'] ?? 'NO')])>
                    <strong>Auto approve = YES</strong> — autopilot may mark specs DONE from REVIEW without human Approve.
                </li>
                <li @class(['is-on' => ! $isYes($s['lucille'] ?? 'YES')])>
                    <strong>Lucille = NO</strong> — no usage-log, no deadline interviews; historical ledger stays readable.
                </li>
                <li @class(['is-on' => ! $isYes($s['decision_log'] ?? 'YES')])>
                    <strong>Decision log = NO</strong> — skip <code>larapilot:decision-log</code> and regression checks.
                </li>
                <li @class(['is-on' => $isYes($s['code_history'] ?? 'NO')])>
                    <strong>Code history = YES</strong> — after each <code>task-done</code>, append file+line touchpoints to <code>.larapilot/code-history.yaml</code>.
                </li>
                <li @class(['is-on' => $isYes($s['release_mode'] ?? 'NO')])>
                    <strong>Release mode = YES</strong> — semver ledger in <code>releases.yaml</code>. Gitflow cuts <code>release/x.y.z</code>, opens feature branches from it, and ships with <code>release-ship</code>.
                </li>
                <li @class(['is-on' => $isYes($s['project_docs'] ?? 'NO')])>
                    <strong>Project docs = YES</strong> — Albert maintains living handbook in <code>_project_docs/</code> after material changes.
                </li>
                <li @class(['is-on' => $isYes($s['comments'] ?? 'NO')])>
                    <strong>Comments = YES</strong> — dashboard spec feedback UI, API comments, and larapilot:spec-comment until DONE.
                </li>
                <li @class(['is-on' => $isYes($s['security_scan'] ?? 'NO')])>
                    <strong>Security scan = YES</strong> — <code>/larapilot-review</code> runs <code>checkpoint:scan</code>; FAIL findings block until fixed or waived.
                </li>
                <li @class(['is-on' => $isYes($s['aikido'] ?? 'NO')])>
                    <strong>Aikido = YES</strong> — <code>/larapilot-aikido</code> hands the findings of Aikido to triage; <code>/larapilot-ship</code> stops on the ones that are open and not waived.
                </li>
                <li @class(['is-on' => $isYes($s['boogle'] ?? 'NO')])>
                    <strong>Boogle = YES</strong> — <code>/larapilot-boogle</code> hands the errors the running application throws to triage, one request for each bug; the Errors page shows them.
                </li>
                <li @class(['is-on' => $isYes($s['notifications'] ?? 'NO')])>
                    <strong>Notifications = YES</strong> — Slack/Discord/Telegram fan-out when channels are configured in <code>.env</code>.
                </li>
            </ul>
        </section>

        <section class="card docs-panel">
            <h2>Skills &amp; outputs</h2>
            <p class="sub">Invoke skills as slash commands in Cursor (Laravel Boost). Each skill loads <code>.larapilot/shared-runtime.md</code> plus the runtime packs it needs.</p>

            <div class="table-wrap">
            <table class="skills-table">
                <thead>
                    <tr>
                        <th>Skill</th>
                        <th>Primary output</th>
                        <th>Lead personas</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><code>/larapilot-inception</code></td><td><code>.larapilot/docs/PRD.md</code>, choices snapshot</td><td>💎 Mark · 🧭 Jennifer · 🤖 Zoey</td></tr>
                    <tr><td><code>/larapilot-adopt</code></td><td>Reverse-engineered PRD + codebase analysis</td><td>💎 Mark · 🔎 Tom · 🔄 Sabrine</td></tr>
                    <tr><td><code>/larapilot-spec</code></td><td><code>backlog.yaml</code>, <code>specs/US-XXX.yaml</code></td><td>💎 Mark · 🔎 Tom</td></tr>
                    <tr><td><code>/larapilot-feature</code></td><td>New user story + optional PRD FR</td><td>💎 Mark · 🔎 Tom</td></tr>
                    <tr><td><code>/larapilot-bug</code></td><td>Fix spec, support intake</td><td>🎧 Sophia · 🔎 Tom · 🧪 Anne</td></tr>
                    <tr><td><code>/larapilot-prd</code></td><td>Revised PRD + revision history row, backlog impact by spec status</td><td>💎 Mark · 🔎 Tom · 🗄️ Mike</td></tr>
                    <tr><td><code>/larapilot-triage</code></td><td>Verdict (bug or feature) + handoff to <code>/larapilot-bug</code> or <code>/larapilot-feature</code></td><td>🎧 Sophia · 💎 Mark · 🔎 Tom</td></tr>
                    <tr><td><code>/larapilot-plan</code></td><td><code>plans/US-XXX-plan.yaml</code></td><td>📐 John · 🧪 Anne · 🗄️ Mike</td></tr>
                    <tr><td><code>/larapilot-design</code> <span class="skill-optional">optional</span></td><td><code>mockups/{spec}/</code>, gallery <a href="{{ route('larapilot.dashboard.design') }}">/larapilot/design</a></td><td>🎨 Elise · ✨ Joe</td></tr>
                    <tr><td><code>/larapilot-implement</code></td><td>Code, tests, atomic commits per git_mode, developer domain docs in <code>.larapilot/docs/devs/</code></td><td>🔧 Alex · 👾 Andrew · ⌨️ Sarah · 📝 Albert</td></tr>
                    <tr><td><code>/larapilot-review</code></td><td>DONE or rework feedback</td><td>🛡️ Robert · 🧪 Anne · 🔐 Lars</td></tr>
                    <tr><td><code>/larapilot-ship</code> <span class="skill-optional">optional</span></td><td>Security gate + deploy + launch checks</td><td>🚀 Jack · 🔐 Lars · ⚖️ Violet</td></tr>
                    <tr><td><code>/larapilot-release</code> <span class="skill-optional">when release_mode</span></td><td><code>releases.yaml</code>, release branches</td><td>⌨️ Sarah · 🚀 Jack · 💎 Mark</td></tr>
                    <tr><td><code>/larapilot-project-docs</code> <span class="skill-optional">when project_docs</span></td><td><code>_project_docs/</code> handbook</td><td>📝 Albert</td></tr>
                    <tr><td><code>/larapilot-settings</code></td><td><code>config.yaml</code> settings</td><td>🤖 Zoey · 💎 Mark · 🚀 Jack · 🔐 Lars · 💰 Aurora</td></tr>
                    <tr><td><code>/larapilot-economics</code> <span class="skill-optional">when account ≠ NONE</span></td><td>Client quote, tax, payback, SaaS ARR — <a href="{{ route('larapilot.dashboard.economics') }}">Economics</a></td><td>💰 Aurora · 📒 Lucille</td></tr>
                    <tr><td><code>/larapilot-usage</code></td><td>Ledger query and Markdown report — token charts on <a href="{{ route('larapilot.dashboard.usage') }}">Usage</a>, schedule and Gantt on <a href="{{ route('larapilot.dashboard.plan') }}">Plan</a></td><td>📒 Lucille · 🤖 Zoey</td></tr>
                    <tr><td><code>/larapilot-autopilot</code></td><td>Batch implement → review loop</td><td>🔧 Alex · 🛡️ Robert · 🤖 Zoey</td></tr>
                    <tr><td><code>/larapilot-frontend-companion</code></td><td>Link external FE repo via <code>.env</code></td><td>✨ Joe · 🔗 Matt</td></tr>
                    <tr><td><code>/larapilot-aikido</code></td><td>Findings of Aikido in <code>docs/security/aikido.md</code>, decisions in <code>aikido.yaml</code>, handoff to <code>/larapilot-triage</code></td><td>🔐 Lars · 🎧 Sophia · 🔗 Matt</td></tr>
                    <tr><td><code>/larapilot-boogle</code></td><td>Errors of Boogle in <code>docs/support/boogle.md</code>, decisions in <code>boogle.yaml</code>, handoff to <code>/larapilot-triage</code></td><td>🎧 Sophia · 🧪 Anne · 🔗 Matt</td></tr>
                    <tr><td><code>/larapilot-tracker</code></td><td>Linear/Jira/… mirror in <code>tracker.yaml</code></td><td>🔗 Matt · 💎 Mark</td></tr>
                    <tr><td><code>/larapilot-backstage</code></td><td>Backstage catalog + TechDocs</td><td>📝 Albert · 🚀 Jack</td></tr>
                    <tr><td><code>/larapilot-custom-skill</code></td><td>User skill under <code>.larapilot/skills/</code> — listed on the <a href="{{ route('larapilot.dashboard.skills') }}">Skills</a> page</td><td>🤖 Zoey · ⌨️ Sarah</td></tr>
                </tbody>
            </table>
            </div>
        </section>

        @if (Route::has('larapilot.dashboard.files.browse') && app(\Larapilot\Services\ConfigService::class)->fileManagerBrowsable())
            <section class="card docs-panel">
                <h2>Material folders</h2>
                <p class="sub">What you hand the skills before they start. Drop files in from the <a href="{{ route('larapilot.dashboard.files') }}">File manager</a> or straight into the repository.</p>

                <div class="folder-list">
                    @foreach (app(\Larapilot\Services\FileManagerService::class)->roots() as $folder)
                        <a href="{{ route('larapilot.dashboard.files.browse', ['root' => $folder['key']]) }}">
                            <code>{{ $folder['path'] }}</code>
                            <span>{{ $folder['description'] }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="card docs-panel">
            <h2>Personas</h2>
            <p class="sub">When an agent speaks in chat, it uses <code>icon + name</code> (e.g. 💎 Mark). Zoey and Lucille are cross-cutting on every skill when enabled.</p>

            <div class="table-wrap">
            <table class="personas-table">
                <thead>
                    <tr><th>Persona</th><th>Role</th></tr>
                </thead>
                <tbody>
                    <tr><td>💎 Mark</td><td>Product Manager — scope, MoSCoW, backlog granularity, PRD edits, comments toggle</td></tr>
                    <tr><td>🔎 Tom</td><td>Requirements Analyst — acceptance criteria, edge cases, FR traceability</td></tr>
                    <tr><td>📐 John</td><td>Architect — SOLID, APIs, queues, multi-tenancy</td></tr>
                    <tr><td>🔧 Alex</td><td>Full-Stack Developer — implementation, factories, per-task commits</td></tr>
                    <tr><td>🧪 Anne</td><td>Test Architect — Pest/PHPUnit depth per <code>settings.testing</code></td></tr>
                    <tr><td>🛡️ Robert</td><td>Code Reviewer — quality gate, plan adherence, Git hygiene</td></tr>
                    <tr><td>🚀 Jack</td><td>DevOps — git_mode, CI/CD, deploy platform, forge integrations</td></tr>
                    <tr><td>⌨️ Sarah</td><td>CLI &amp; Git expert — conflicts, release branches, forge CLIs, pipeline scripts</td></tr>
                    <tr><td>🔐 Lars</td><td>Security — OWASP, dashboard/API auth, checkpoint scan gate, Aikido findings and waivers</td></tr>
                    <tr><td>🎨 Elise · ✨ Joe</td><td>UX &amp; Frontend — design systems, mockups, responsive/WCAG UI</td></tr>
                    <tr><td>📝 Albert</td><td>Tech Writer — OpenAPI, diagrams, <code>_project_docs/</code> when enabled</td></tr>
                    <tr><td>📒 Lucille</td><td>Project tracking — token/hour ledger on Usage, deadlines and Gantt on Plan (default ON)</td></tr>
                    <tr><td>🤖 Zoey</td><td>AI Guru — prompt sharpening, output economy, sub-agent orchestration (every skill)</td></tr>
                    <tr><td>🔄 Sabrine</td><td>Legacy porting — brownfield inventory, parity checks</td></tr>
                    <tr><td>🗄️ Mike</td><td>Database — schema, migrations, search, data architecture</td></tr>
                    <tr><td>🔗 Matt</td><td>Integrations — OAuth, webhooks, tracker sync, notifications</td></tr>
                    <tr><td>🎧 Sophia</td><td>Support — post-ship bug intake and triage</td></tr>
                    <tr><td>💰 Aurora</td><td>FinOps — Account mode, Economics quotes, country tax, SaaS ARR</td></tr>
                    <tr><td>⚖️ Violet · 📈 Emma</td><td>Legal/privacy and SEO &amp; performance — mainly ship/discovery gates</td></tr>
                </tbody>
            </table>
            </div>
        </section>
    </div>
@endsection
