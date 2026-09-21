@extends('larapilot::dashboard.layout')

@section('title', 'Docs')

@push('styles')
<style>
    body .shell:has(.docs-page) {
        max-width: none;
        padding-left: max(20px, 4vw);
        padding-right: max(20px, 4vw);
    }

    .docs-page {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .docs-panel {
        padding: 24px 28px;
    }

    .docs-panel h2 {
        margin: 0 0 8px;
        font-size: 1.15rem;
    }

    .docs-panel h3 {
        margin: 24px 0 8px;
        font-size: 1rem;
    }

    .docs-panel .sub {
        margin: 0 0 20px;
        color: var(--muted);
        font-size: 0.9rem;
        max-width: 80ch;
        line-height: 1.55;
    }

    .flow-steps {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 12px;
        margin: 0 0 8px;
    }

    .flow-step {
        padding: 14px 16px;
        border-radius: 10px;
        border: 1px solid var(--border);
        background: color-mix(in srgb, var(--surface) 92%, var(--bg));
    }

    .flow-step strong {
        display: block;
        font-size: 0.82rem;
        margin-bottom: 4px;
    }

    .flow-step span {
        font-size: 0.78rem;
        color: var(--muted);
    }

    .branch-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: 10px;
    }

    .branch-list li {
        padding: 12px 14px;
        border-radius: 10px;
        border: 1px solid var(--border);
        font-size: 0.875rem;
        line-height: 1.5;
    }

    .branch-list li.is-on {
        border-color: var(--accent);
        background: var(--accent-soft);
    }

    .branch-list code {
        font-size: 0.78rem;
    }

    .skills-table,
    .personas-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .skills-table th,
    .skills-table td,
    .personas-table th,
    .personas-table td {
        text-align: left;
        padding: 10px 12px;
        border-top: 1px solid var(--border);
        vertical-align: top;
    }

    .skills-table th,
    .personas-table th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--muted);
        border-top: 0;
    }

    .skills-table code,
    .personas-table code {
        font-size: 0.78rem;
        white-space: nowrap;
    }

    .skill-optional {
        color: var(--muted);
        font-size: 0.78rem;
    }

    @media (max-width: 768px) {
        .skills-table,
        .personas-table {
            display: block;
            overflow-x: auto;
        }
    }
</style>
@endpush

@section('content')
    @php
        $s = $settings ?? [];
        $isYes = static fn (mixed $value): bool => $value === 'YES' || $value === true;
    @endphp

    <div class="docs-page">
        <section class="card docs-panel">
            <h2>How Larapilot works</h2>
            <p class="sub">Larapilot turns your AI agent into a spec-driven product squad. Boost skills orchestrate the conversation; <code>php artisan larapilot:*</code> persists artifacts; <code>.larapilot/</code> in the repo is the source of truth between sessions.</p>

            <h3>Core delivery loop</h3>
            <div class="flow-steps">
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
                <div class="flow-step"><strong>Autopilot</strong><span><code>/larapilot-autopilot</code> — batch implement/review</span></div>
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
                    <strong>Release mode = YES</strong> — semver ledger in <code>releases.yaml</code>, <code>/larapilot-release</code>, Gitflow <code>release/x.y.z</code> branches.
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
                <li @class(['is-on' => $isYes($s['notifications'] ?? 'NO')])>
                    <strong>Notifications = YES</strong> — Slack/Discord/Telegram fan-out when channels are configured in <code>.env</code>.
                </li>
            </ul>
        </section>

        <section class="card docs-panel">
            <h2>Skills &amp; outputs</h2>
            <p class="sub">Invoke skills as slash commands in Cursor (Laravel Boost). Each skill loads <code>.larapilot/shared-runtime.md</code> plus the runtime packs it needs.</p>

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
                    <tr><td><code>/larapilot-plan</code></td><td><code>plans/US-XXX-plan.yaml</code></td><td>📐 John · 🧪 Anne · 🗄️ Mike</td></tr>
                    <tr><td><code>/larapilot-design</code> <span class="skill-optional">optional</span></td><td><code>mockups/{spec}/</code>, gallery <a href="{{ route('larapilot.dashboard.design') }}">/larapilot/design</a></td><td>🎨 Elise · ✨ Joe</td></tr>
                    <tr><td><code>/larapilot-implement</code></td><td>Code, tests, atomic commits per git_mode</td><td>🔧 Alex · 👾 Andrew · ⌨️ Sarah</td></tr>
                    <tr><td><code>/larapilot-review</code></td><td>DONE or rework feedback</td><td>🛡️ Robert · 🧪 Anne · 🔐 Lars</td></tr>
                    <tr><td><code>/larapilot-ship</code> <span class="skill-optional">optional</span></td><td>Security gate + deploy + launch checks</td><td>🚀 Jack · 🔐 Lars · ⚖️ Violet</td></tr>
                    <tr><td><code>/larapilot-release</code> <span class="skill-optional">when release_mode</span></td><td><code>releases.yaml</code>, release branches</td><td>⌨️ Sarah · 🚀 Jack · 💎 Mark</td></tr>
                    <tr><td><code>/larapilot-project-docs</code> <span class="skill-optional">when project_docs</span></td><td><code>_project_docs/</code> handbook</td><td>📝 Albert</td></tr>
                    <tr><td><code>/larapilot-settings</code></td><td><code>config.yaml</code> settings</td><td>🤖 Zoey · 💎 Mark · 🚀 Jack · 🔐 Lars · 💰 Aurora</td></tr>
                    <tr><td><code>/larapilot-economics</code> <span class="skill-optional">when account ≠ NONE</span></td><td>Client quote, tax, payback, SaaS ARR — <a href="{{ route('larapilot.dashboard.economics') }}">Economics</a></td><td>💰 Aurora · 📒 Lucille</td></tr>
                    <tr><td><code>/larapilot-usage</code></td><td>Ledger query, Gantt, Markdown report</td><td>📒 Lucille · 🤖 Zoey</td></tr>
                    <tr><td><code>/larapilot-autopilot</code></td><td>Batch implement → review loop</td><td>🔧 Alex · 🛡️ Robert · 🤖 Zoey</td></tr>
                    <tr><td><code>/larapilot-frontend-companion</code></td><td>Link external FE repo via <code>.env</code></td><td>✨ Joe · 🔗 Matt</td></tr>
                    <tr><td><code>/larapilot-tracker</code></td><td>Linear/Jira/… mirror in <code>tracker.yaml</code></td><td>🔗 Matt · 💎 Mark</td></tr>
                    <tr><td><code>/larapilot-backstage</code></td><td>Backstage catalog + TechDocs</td><td>📝 Albert · 🚀 Jack</td></tr>
                    <tr><td><code>/larapilot-custom-skill</code></td><td>User skill under <code>.larapilot/skills/</code> — listed on the <a href="{{ route('larapilot.dashboard.skills') }}">Skills</a> page</td><td>🤖 Zoey · ⌨️ Sarah</td></tr>
                </tbody>
            </table>
        </section>

        <section class="card docs-panel">
            <h2>Personas</h2>
            <p class="sub">When an agent speaks in chat, it uses <code>icon + name</code> (e.g. 💎 Mark). Zoey and Lucille are cross-cutting on every skill when enabled.</p>

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
                    <tr><td>🔐 Lars</td><td>Security — OWASP, dashboard/API auth, checkpoint scan gate</td></tr>
                    <tr><td>🎨 Elise · ✨ Joe</td><td>UX &amp; Frontend — design systems, mockups, responsive/WCAG UI</td></tr>
                    <tr><td>📝 Albert</td><td>Tech Writer — OpenAPI, diagrams, <code>_project_docs/</code> when enabled</td></tr>
                    <tr><td>📒 Lucille</td><td>Project tracking — token/hour ledger, deadlines, Usage dashboard (default ON)</td></tr>
                    <tr><td>🤖 Zoey</td><td>AI Guru — prompt sharpening, output economy, sub-agent orchestration (every skill)</td></tr>
                    <tr><td>🔄 Sabrine</td><td>Legacy porting — brownfield inventory, parity checks</td></tr>
                    <tr><td>🗄️ Mike</td><td>Database — schema, migrations, search, data architecture</td></tr>
                    <tr><td>🔗 Matt</td><td>Integrations — OAuth, webhooks, tracker sync, notifications</td></tr>
                    <tr><td>🎧 Sophia</td><td>Support — post-ship bug intake and triage</td></tr>
                    <tr><td>💰 Aurora</td><td>FinOps — Account mode, Economics quotes, country tax, SaaS ARR</td></tr>
                    <tr><td>⚖️ Violet · 📈 Emma</td><td>Legal/privacy and SEO &amp; performance — mainly ship/discovery gates</td></tr>
                </tbody>
            </table>
        </section>
    </div>
@endsection
