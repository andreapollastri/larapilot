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
    ],

    'mockups_route' => [
        'enabled' => env('LARAPILOT_MOCKUPS_ROUTE', true),
        'prefix' => 'mockups',
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
        'requirements_analyst' => ['name' => 'Mark', 'icon' => '🔎', 'role' => 'Requirements Analyst'],
        'architect' => ['name' => 'John', 'icon' => '📐', 'role' => 'Architect'],
        'developer' => ['name' => 'Alex', 'icon' => '🔧', 'role' => 'Full-Stack Developer'],
        'test_architect' => ['name' => 'Anne', 'icon' => '🧪', 'role' => 'Test Architect'],
        'code_reviewer' => ['name' => 'Robert', 'icon' => '🛡️', 'role' => 'Code Reviewer'],
        'ux_designer' => ['name' => 'Elise', 'icon' => '🎨', 'role' => 'UX Designer'],
    ],
];
