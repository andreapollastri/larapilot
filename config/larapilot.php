<?php

declare(strict_types=1);

return [
    'enabled' => env('LARAPILOT_ENABLED', true),

    'data_directory' => base_path('.larapilot'),

    'connector' => env('LARAPILOT_CONNECTOR', 'file'),

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
    ],

    'mockups_route' => [
        'enabled' => env('LARAPILOT_MOCKUPS_ROUTE', true),
        'prefix' => 'mockups',
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
        'integration_manager' => ['name' => 'Matt', 'icon' => '🔗', 'role' => 'Integration Manager'],
        'ethical_hacker' => ['name' => 'Oliver', 'icon' => '🎯', 'role' => 'Ethical Hacker'],
        'support_manager' => ['name' => 'Sophia', 'icon' => '🎧', 'role' => 'Support Manager'],
        'translator' => ['name' => 'Emily', 'icon' => '🌍', 'role' => 'Translator'],
    ],
];
