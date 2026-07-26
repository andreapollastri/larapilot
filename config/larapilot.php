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
    ],
];
