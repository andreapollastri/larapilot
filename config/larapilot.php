<?php

declare(strict_types=1);

return [
    'enabled' => env('LARAPILOT_ENABLED', true),

    'data_directory' => base_path('.larapilot'),

    'connector' => env('LARAPILOT_CONNECTOR', 'file'),

    'settings' => [
        'effort' => 'STANDARD',
        'backlog' => 'STANDARD',
        'git_mode' => 'GITFLOW',
        'testing' => 'NORMAL',
        'auto_approve' => false,
        // Lucille · Project tracking (usage ledger + schedule) is ON by default; set false to exclude explicitly.
        'lucille' => true,
        // Decision journal (.larapilot/decisions.yaml) — append-only log of every explicit user
        // choice across all phases, plus a regression guard that flags a new value when the same
        // topic already carries a recorded decision. ON by default; set false to disable.
        'decision_log' => true,
        // Code change history (.larapilot/code-history.yaml) — per spec/task list of files and line
        // ranges touched, derived from the task git commit. OFF by default; set true to enable.
        'code_history' => false,
        // HTTP Basic Auth on the /larapilot dashboard UI — OFF by default (open in the
        // allowed environments). When true, browsing the dashboard requires a username +
        // password from .larapilot/auth.yaml (manage with `larapilot:dashboard-user`).
        // Never affects the JSON API (LARAPILOT_API_TOKEN) or the MCP server.
        'dashboard_auth' => false,
        // Optional remote forges + chat notifications — all OFF by default.
        'github' => false,
        'gitlab' => false,
        'bitbucket' => false,
        'azure' => false,
        'notifications' => false,
        'notify_slack' => false,
        'notify_discord' => false,
        'notify_telegram' => false,
    ],

    // Secrets for optional integrations (never commit these).
    'integrations' => [
        'slack_webhook_url' => env('LARAPILOT_SLACK_WEBHOOK_URL'),
        'discord_webhook_url' => env('LARAPILOT_DISCORD_WEBHOOK_URL'),
        'telegram_bot_token' => env('LARAPILOT_TELEGRAM_BOT_TOKEN'),
        'telegram_chat_id' => env('LARAPILOT_TELEGRAM_CHAT_ID'),
        // Bitbucket Cloud (optional; also accepted as BITBUCKET_* without prefix).
        'bitbucket_username' => env('LARAPILOT_BITBUCKET_USERNAME', env('BITBUCKET_USERNAME')),
        'bitbucket_app_password' => env('LARAPILOT_BITBUCKET_APP_PASSWORD', env('BITBUCKET_APP_PASSWORD')),
        'bitbucket_access_token' => env('LARAPILOT_BITBUCKET_ACCESS_TOKEN', env('BITBUCKET_ACCESS_TOKEN')),
        // Azure DevOps (optional; also accepted as AZURE_DEVOPS_EXT_PAT / AZURE_DEVOPS_PAT).
        'azure_devops_pat' => env('LARAPILOT_AZURE_DEVOPS_PAT', env('AZURE_DEVOPS_EXT_PAT', env('AZURE_DEVOPS_PAT'))),
    ],

    // External frontend repository (when PRD topology is API + external frontend).
    // Persisted in .larapilot/config.yaml via larapilot:frontend-set.
    'frontend' => [
        'repo_path' => null,
        'stack' => null,
    ],

    'paths' => [
        'prd' => '.larapilot/docs/PRD.md',
        'mockups' => '.larapilot/mockups/',
        'test_results' => '.larapilot/docs/test-results/',
        'review' => '.larapilot/docs/review/',
        'security' => '.larapilot/docs/security/',
        'launch' => '.larapilot/docs/launch/',
        'support' => '.larapilot/docs/support/',
        'client_materials' => '.larapilot/client-materials/',
        'legacy' => '.larapilot/legacy/',
        'research' => '.larapilot/research/',
        'design_systems' => '.larapilot/design-systems/',
        'internal_feedback' => '.larapilot/internal-feedback/',
        'usage' => '.larapilot/usage/',
        'choices' => '.larapilot/choices.yaml',
        'schedule' => '.larapilot/usage/schedule.yaml',
        'decisions' => '.larapilot/decisions.yaml',
        'code_history' => '.larapilot/code-history.yaml',
    ],

    'comments' => [
        'enabled' => env('LARAPILOT_COMMENTS_ENABLED', true),
    ],

    'api' => [
        // Optional shared token for the JSON API. When set, every request to
        // /larapilot/api/* must send it as a bearer token or X-Larapilot-Token
        // header. Strongly recommended on shared staging hosts.
        'token' => env('LARAPILOT_API_TOKEN'),
    ],

    'diagnostics' => [
        'enabled' => env('LARAPILOT_DIAGNOSTICS_ENABLED', true),
        'default_log_lines' => (int) env('LARAPILOT_DIAGNOSTICS_LOG_LINES', 100),
        'max_log_lines' => (int) env('LARAPILOT_DIAGNOSTICS_MAX_LOG_LINES', 500),
    ],

    // Backstage (backstage.io) software catalog integration. Turns the
    // .larapilot/ workspace into a catalog-info.yaml descriptor, a TechDocs
    // site, and a live delivery snapshot for a Backstage plugin or entity
    // provider. Nothing is written until larapilot:backstage-export --write.
    'backstage' => [
        'enabled' => env('LARAPILOT_BACKSTAGE_ENABLED', true),

        // Entity identity. `name` defaults to a slug of app.name; `title` and
        // `description` fall back to app.name and the PRD elevator pitch.
        'name' => env('LARAPILOT_BACKSTAGE_NAME'),
        'namespace' => env('LARAPILOT_BACKSTAGE_NAMESPACE', 'default'),
        'title' => env('LARAPILOT_BACKSTAGE_TITLE'),
        'description' => env('LARAPILOT_BACKSTAGE_DESCRIPTION'),

        // Backstage requires an owner on Component/API entities. Accepts a
        // bare group name ("platform") or a full ref ("group:default/platform").
        'owner' => env('LARAPILOT_BACKSTAGE_OWNER', 'guests'),

        // Optional parent System entity ref ("checkout" or "system:default/checkout").
        'system' => env('LARAPILOT_BACKSTAGE_SYSTEM'),

        'component_type' => env('LARAPILOT_BACKSTAGE_COMPONENT_TYPE', 'service'),
        'lifecycle' => env('LARAPILOT_BACKSTAGE_LIFECYCLE', 'experimental'),
        'tags' => ['laravel', 'larapilot'],

        // Absolute base URL used for catalog links/annotations (board + API).
        // Falls back to app.url; the HTTP endpoints use the request host.
        'base_url' => env('LARAPILOT_BACKSTAGE_BASE_URL'),

        // Files written into the project by larapilot:backstage-export --write.
        'catalog_file' => 'catalog-info.yaml',
        'mkdocs_file' => 'mkdocs.yml',

        'techdocs' => [
            'enabled' => env('LARAPILOT_BACKSTAGE_TECHDOCS', true),
            'docs_dir' => '.larapilot/techdocs',
        ],

        // Register the dev-only Larapilot workflow API as its own Backstage
        // API entity. Off by default: it is tooling, not a product contract.
        'workflow_api' => env('LARAPILOT_BACKSTAGE_WORKFLOW_API', false),
    ],

    // Project-tracker integration. Pushes the backlog into Linear, Asana,
    // Jira, Trello, ClickUp, or Monday so non-developers can follow delivery
    // in the tool they already use. Off until a provider is configured.
    //
    // Direction: .larapilot/ is the source of truth. Push writes stories and
    // plan tasks out; pull reads remote state back as a drift report and only
    // touches the backlog with an explicit --apply.
    //
    // Credentials come from env and are never written into .larapilot/.
    'tracker' => [
        'enabled' => env('LARAPILOT_TRACKER_ENABLED', false),

        // linear | asana | jira | trello | clickup | monday
        'provider' => env('LARAPILOT_TRACKER_PROVIDER'),

        // Seconds before an API call to the provider is abandoned.
        'timeout' => (int) env('LARAPILOT_TRACKER_TIMEOUT', 15),

        // Mirror plan tasks as native sub-issues/subtasks under each story.
        'sync_tasks' => env('LARAPILOT_TRACKER_SYNC_TASKS', true),

        'pull' => [
            // Map remote status back to a Larapilot status in the drift report.
            'statuses' => env('LARAPILOT_TRACKER_PULL_STATUSES', true),
            // Import remote comments as internal feedback (never blocking).
            'comments' => env('LARAPILOT_TRACKER_PULL_COMMENTS', false),
        ],

        'providers' => [
            // https://linear.app — Settings → API → Personal API keys.
            // status_map values are Linear workflow state names on the team.
            'linear' => [
                'api_key' => env('LARAPILOT_LINEAR_API_KEY'),
                'team' => env('LARAPILOT_LINEAR_TEAM'),          // team key, e.g. ENG
                'project' => env('LARAPILOT_LINEAR_PROJECT'),    // optional project id
                'status_map' => [
                    'TODO' => 'Todo',
                    'PLANNED' => 'Todo',
                    'IN PROGRESS' => 'In Progress',
                    'REVIEW' => 'In Review',
                    'DONE' => 'Done',
                ],
            ],

            // https://asana.com — personal access token.
            // status_map values are section names inside the project.
            'asana' => [
                'api_key' => env('LARAPILOT_ASANA_TOKEN'),
                'project' => env('LARAPILOT_ASANA_PROJECT'),     // project gid
                'status_map' => [
                    'TODO' => 'To Do',
                    'PLANNED' => 'To Do',
                    'IN PROGRESS' => 'In Progress',
                    'REVIEW' => 'In Review',
                    'DONE' => 'Done',
                ],
            ],

            // https://atlassian.com — Jira Cloud, REST v2, Basic auth with an
            // account email plus an API token. status_map values are workflow
            // status names; the driver resolves the matching transition.
            'jira' => [
                'base_url' => env('LARAPILOT_JIRA_BASE_URL'),    // https://acme.atlassian.net
                'email' => env('LARAPILOT_JIRA_EMAIL'),
                'api_key' => env('LARAPILOT_JIRA_API_TOKEN'),
                'project' => env('LARAPILOT_JIRA_PROJECT'),      // project key, e.g. LP
                'issue_type' => env('LARAPILOT_JIRA_ISSUE_TYPE', 'Task'),
                'subtask_type' => env('LARAPILOT_JIRA_SUBTASK_TYPE', 'Sub-task'),
                'status_map' => [
                    'TODO' => 'To Do',
                    'PLANNED' => 'To Do',
                    'IN PROGRESS' => 'In Progress',
                    'REVIEW' => 'In Review',
                    'DONE' => 'Done',
                ],
            ],

            // https://trello.com — API key plus token. Statuses are lists
            // (board columns); plan tasks become checklist items.
            'trello' => [
                'api_key' => env('LARAPILOT_TRELLO_KEY'),
                'token' => env('LARAPILOT_TRELLO_TOKEN'),
                'board' => env('LARAPILOT_TRELLO_BOARD'),        // board id
                'checklist' => env('LARAPILOT_TRELLO_CHECKLIST', 'Plan tasks'),
                'status_map' => [
                    'TODO' => 'To Do',
                    'PLANNED' => 'To Do',
                    'IN PROGRESS' => 'In Progress',
                    'REVIEW' => 'In Review',
                    'DONE' => 'Done',
                ],
            ],

            // https://clickup.com — personal token (pk_…). status_map values
            // are list statuses.
            'clickup' => [
                'api_key' => env('LARAPILOT_CLICKUP_TOKEN'),
                'list' => env('LARAPILOT_CLICKUP_LIST'),         // list id
                'status_map' => [
                    'TODO' => 'to do',
                    'PLANNED' => 'to do',
                    'IN PROGRESS' => 'in progress',
                    'REVIEW' => 'review',
                    'DONE' => 'complete',
                ],
            ],

            // https://monday.com — API token. status_map values are labels on
            // the board's status column.
            'monday' => [
                'api_key' => env('LARAPILOT_MONDAY_TOKEN'),
                'board' => env('LARAPILOT_MONDAY_BOARD'),        // board id
                'group' => env('LARAPILOT_MONDAY_GROUP'),        // optional group id
                'status_column' => env('LARAPILOT_MONDAY_STATUS_COLUMN', 'status'),
                // Monday items have no description field. Point this at a
                // long-text column id to carry the spec body; without it only
                // the title, status, and subitems are pushed.
                'description_column' => env('LARAPILOT_MONDAY_DESCRIPTION_COLUMN'),
                'api_version' => env('LARAPILOT_MONDAY_API_VERSION', '2025-04'),
                'status_map' => [
                    'TODO' => 'Not Started',
                    'PLANNED' => 'Not Started',
                    'IN PROGRESS' => 'Working on it',
                    'REVIEW' => 'Working on it',
                    'DONE' => 'Done',
                ],
            ],
        ],
    ],

    'mockups_route' => [
        'enabled' => env('LARAPILOT_MOCKUPS_ROUTE', true),
        'prefix' => 'mockups',
        'middleware' => ['web'],
        'environments' => ['local', 'development', 'testing', 'staging'],
    ],

    'mockup_assets_route' => [
        'enabled' => env('LARAPILOT_MOCKUPS_ROUTE', true),
        'prefix' => 'mockup-assets',
        'middleware' => ['web'],
        'environments' => ['local', 'development', 'testing', 'staging'],
    ],

    'dashboard_route' => [
        'enabled' => env('LARAPILOT_DASHBOARD_ROUTE', true),
        'prefix' => 'larapilot',
        'middleware' => ['web'],
        'environments' => ['local', 'development', 'testing', 'staging'],

        // Optional HTTP Basic Auth for the dashboard UI. Enforced only when the
        // `dashboard_auth` project setting is ON (see settings above). Credentials
        // live hashed in `file`, which is git-ignored automatically and never
        // committed. Manage users with `php artisan larapilot:dashboard-user`.
        // This gate never applies to /larapilot/api/* or the MCP server.
        'auth' => [
            'file' => base_path('.larapilot/auth.yaml'),
            'realm' => env('LARAPILOT_DASHBOARD_AUTH_REALM', 'Larapilot'),
            // Failed Basic Auth attempts allowed per minute per IP; 0 disables throttling.
            'max_attempts' => (int) env('LARAPILOT_DASHBOARD_AUTH_MAX_ATTEMPTS', 30),
        ],
    ],

    'workflow' => [
        'statuses' => [
            'todo' => 'TODO',
            'planned' => 'PLANNED',
            'in_progress' => 'IN PROGRESS',
            'review' => 'REVIEW',
            'done' => 'DONE',
        ],
    ],

    'file' => [
        'backlog' => '.larapilot/backlog.yaml',
        'specs' => '.larapilot/specs/',
        'planning' => '.larapilot/plans/',
    ],

    'personas' => [
        'product_manager' => ['name' => 'Mark', 'icon' => '💎', 'role' => 'Product Manager'],
        'business_strategist' => ['name' => 'Jennifer', 'icon' => '🧭', 'role' => 'Business Strategist'],
        'business_consultant' => ['name' => 'Benjamin', 'icon' => '🏢', 'role' => 'Business Consultant'],
        'innovator' => ['name' => 'Sebastian', 'icon' => '💡', 'role' => 'Innovator'],
        'requirements_analyst' => ['name' => 'Tom', 'icon' => '🔎', 'role' => 'Requirements Analyst'],
        'architect' => ['name' => 'John', 'icon' => '📐', 'role' => 'Architect'],
        'developer' => ['name' => 'Alex', 'icon' => '🔧', 'role' => 'Full-Stack Developer'],
        'test_architect' => ['name' => 'Anne', 'icon' => '🧪', 'role' => 'Test Architect'],
        'code_reviewer' => ['name' => 'Robert', 'icon' => '🛡️', 'role' => 'Code Reviewer'],
        'security_expert' => ['name' => 'Lars', 'icon' => '🔐', 'role' => 'Security Expert'],
        'devops' => ['name' => 'Jack', 'icon' => '🚀', 'role' => 'DevOps Engineer'],
        'seo_expert' => ['name' => 'Emma', 'icon' => '📈', 'role' => 'SEO & Web Performance Specialist'],
        'social_media_manager' => ['name' => 'Lauren', 'icon' => '💬', 'role' => 'Social Media Manager'],
        'legal_expert' => ['name' => 'Violet', 'icon' => '⚖️', 'role' => 'Legal Expert'],
        'finops' => ['name' => 'Aurora', 'icon' => '💰', 'role' => 'FinOps Expert'],
        'ux_designer' => ['name' => 'Elise', 'icon' => '🎨', 'role' => 'UX Designer'],
        'frontend_expert' => ['name' => 'Joe', 'icon' => '✨', 'role' => 'Frontend Expert'],
        'app_developer' => ['name' => 'Ricky', 'icon' => '📱', 'role' => 'App Developer'],
        'tech_writer' => ['name' => 'Albert', 'icon' => '📝', 'role' => 'Tech Writer'],
        'ai_guru' => ['name' => 'Zoey', 'icon' => '🤖', 'role' => 'AI Guru'],
        'copywriter' => ['name' => 'Marika', 'icon' => '✍️', 'role' => 'Copywriter'],
        'legacy_porting_specialist' => ['name' => 'Sabrine', 'icon' => '🔄', 'role' => 'Legacy Porting & Migration Specialist'],
        'laravel_expert' => ['name' => 'Andrew', 'icon' => '👾', 'role' => 'Laravel Expert'],
        'integration_manager' => ['name' => 'Matt', 'icon' => '🔗', 'role' => 'Integration Manager'],
        'ethical_hacker' => ['name' => 'Oliver', 'icon' => '🎯', 'role' => 'Ethical Hacker'],
        'support_manager' => ['name' => 'Sophia', 'icon' => '🎧', 'role' => 'Support Manager'],
        'translator' => ['name' => 'Emily', 'icon' => '🌍', 'role' => 'Translator'],
        'database_expert' => ['name' => 'Mike', 'icon' => '🗄️', 'role' => 'Database Expert'],
        'account' => ['name' => 'Lucille', 'icon' => '📒', 'role' => 'Project tracking'],
        'cli_expert' => ['name' => 'Sarah', 'icon' => '⌨️', 'role' => 'CLI, Git & Linux Expert'],
    ],
];
