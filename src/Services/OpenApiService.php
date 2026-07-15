<?php

declare(strict_types=1);

namespace Larapilot\Services;

class OpenApiService
{
    /**
     * @return array<string, mixed>
     */
    public function document(string $baseUrl): array
    {
        $baseUrl = rtrim($baseUrl, '/');

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Larapilot Workflow API',
                'version' => '1.0.0',
                'description' => 'Read-only JSON API for the Larapilot workflow board. '
                    .'Exposes backlog specs (user stories), plans, tasks, and the PRD from `.larapilot/` artifacts. '
                    .'Available only in the same environments where the `/larapilot` dashboard is browsable (never in production).',
            ],
            'servers' => [
                ['url' => $baseUrl],
            ],
            'tags' => [
                ['name' => 'Board', 'description' => 'Kanban board overview'],
                ['name' => 'Specs', 'description' => 'User stories (backlog specs)'],
                ['name' => 'PRD', 'description' => 'Product Requirements Document'],
            ],
            'paths' => [
                '/board' => [
                    'get' => [
                        'tags' => ['Board'],
                        'summary' => 'Full board snapshot',
                        'description' => 'Returns metrics, workflow status order, and all user stories grouped by status column.',
                        'operationId' => 'getBoard',
                        'responses' => [
                            '200' => [
                                'description' => 'Board snapshot',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/BoardResponse'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                '/specs' => [
                    'get' => [
                        'tags' => ['Specs'],
                        'summary' => 'List user stories',
                        'description' => 'Returns all backlog specs. Optionally filter by workflow status label (e.g. `TODO`, `IN PROGRESS`).',
                        'operationId' => 'listSpecs',
                        'parameters' => [
                            [
                                'name' => 'status',
                                'in' => 'query',
                                'required' => false,
                                'description' => 'Filter by spec status label (case-insensitive)',
                                'schema' => ['type' => 'string', 'example' => 'TODO'],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Spec list',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/SpecListResponse'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                '/specs/{code}' => [
                    'get' => [
                        'tags' => ['Specs'],
                        'summary' => 'Show a user story',
                        'description' => 'Returns the full spec, plan summary, tasks, workdir, and task progress.',
                        'operationId' => 'showSpec',
                        'parameters' => [
                            [
                                'name' => 'code',
                                'in' => 'path',
                                'required' => true,
                                'description' => 'Spec code (e.g. US-001)',
                                'schema' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9][A-Za-z0-9._-]*$'],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Spec detail',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/SpecDetailResponse'],
                                    ],
                                ],
                            ],
                            '404' => ['$ref' => '#/components/responses/NotFound'],
                        ],
                    ],
                ],
                '/prd' => [
                    'get' => [
                        'tags' => ['PRD'],
                        'summary' => 'Show the PRD',
                        'description' => 'Returns the Product Requirements Document markdown and parsed section headings.',
                        'operationId' => 'showPrd',
                        'responses' => [
                            '200' => [
                                'description' => 'PRD content',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/PrdResponse'],
                                    ],
                                ],
                            ],
                            '404' => ['$ref' => '#/components/responses/NotFound'],
                        ],
                    ],
                ],
            ],
            'components' => [
                'responses' => [
                    'NotFound' => [
                        'description' => 'Resource not found',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => ['type' => 'string', 'example' => 'Not found.'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'schemas' => [
                    'TaskProgress' => [
                        'type' => 'object',
                        'properties' => [
                            'total' => ['type' => 'integer', 'minimum' => 0],
                            'done' => ['type' => 'integer', 'minimum' => 0],
                        ],
                        'required' => ['total', 'done'],
                    ],
                    'MockupSummary' => [
                        'type' => 'object',
                        'properties' => [
                            'available' => ['type' => 'boolean'],
                            'path' => ['type' => 'string', 'example' => '.larapilot/mockups/US-001/'],
                            'screen_count' => ['type' => 'integer', 'minimum' => 0],
                            'entry' => ['type' => 'string', 'nullable' => true, 'example' => 'index.html'],
                            'entry_url' => ['type' => 'string', 'nullable' => true, 'example' => '/mockups/US-001'],
                            'browsable' => ['type' => 'boolean'],
                        ],
                        'required' => ['available', 'screen_count'],
                    ],
                    'MockupScreen' => [
                        'type' => 'object',
                        'properties' => [
                            'file' => ['type' => 'string', 'example' => 'index.html'],
                            'label' => ['type' => 'string', 'example' => 'Index'],
                            'url' => ['type' => 'string', 'nullable' => true, 'example' => '/mockups/US-001'],
                        ],
                        'required' => ['file', 'label'],
                    ],
                    'MockupDetail' => [
                        'type' => 'object',
                        'properties' => [
                            'path' => ['type' => 'string'],
                            'entry' => ['type' => 'string', 'nullable' => true],
                            'entry_url' => ['type' => 'string', 'nullable' => true],
                            'browsable' => ['type' => 'boolean'],
                            'screens' => [
                                'type' => 'array',
                                'items' => ['$ref' => '#/components/schemas/MockupScreen'],
                            ],
                        ],
                        'required' => ['path', 'screens', 'browsable'],
                    ],
                    'FeedbackSummary' => [
                        'type' => 'object',
                        'properties' => [
                            'enabled' => ['type' => 'boolean'],
                            'available' => ['type' => 'boolean'],
                            'entry_count' => ['type' => 'integer', 'minimum' => 0],
                            'blocking_count' => ['type' => 'integer', 'minimum' => 0],
                            'writable' => ['type' => 'boolean'],
                            'path' => ['type' => 'string', 'example' => '.larapilot/internal-feedback/US-001.md'],
                        ],
                        'required' => ['enabled', 'entry_count', 'blocking_count', 'writable', 'path'],
                    ],
                    'FeedbackEntry' => [
                        'type' => 'object',
                        'properties' => [
                            'at' => ['type' => 'string'],
                            'author' => ['type' => 'string'],
                            'status' => ['type' => 'string'],
                            'body' => ['type' => 'string'],
                            'blocks_merge' => ['type' => 'boolean'],
                        ],
                        'required' => ['at', 'author', 'status', 'body', 'blocks_merge'],
                    ],
                    'FeedbackDetail' => [
                        'type' => 'object',
                        'properties' => [
                            'enabled' => ['type' => 'boolean'],
                            'writable' => ['type' => 'boolean'],
                            'path' => ['type' => 'string'],
                            'entry_count' => ['type' => 'integer', 'minimum' => 0],
                            'blocking_count' => ['type' => 'integer', 'minimum' => 0],
                            'content' => ['type' => 'string', 'nullable' => true],
                            'html' => ['type' => 'string', 'nullable' => true],
                            'entries' => [
                                'type' => 'array',
                                'items' => ['$ref' => '#/components/schemas/FeedbackEntry'],
                            ],
                        ],
                        'required' => ['enabled', 'writable', 'path', 'entry_count', 'blocking_count', 'entries'],
                    ],
                    'Epic' => [
                        'type' => 'object',
                        'properties' => [
                            'code' => ['type' => 'string'],
                            'title' => ['type' => 'string'],
                        ],
                    ],
                    'SpecSummary' => [
                        'type' => 'object',
                        'properties' => [
                            'code' => ['type' => 'string', 'example' => 'US-001'],
                            'title' => ['type' => 'string'],
                            'priority' => ['type' => 'string', 'enum' => ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW']],
                            'points' => ['type' => 'integer', 'minimum' => 0],
                            'status' => ['type' => 'string', 'example' => 'TODO'],
                            'body' => ['type' => 'string', 'description' => 'Markdown body with User Story, Demonstrates, and Acceptance Criteria sections'],
                            'epic' => ['$ref' => '#/components/schemas/Epic'],
                            'status_history' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'status' => ['type' => 'string'],
                                        'at' => ['type' => 'string', 'format' => 'date-time'],
                                    ],
                                ],
                            ],
                            'rework' => ['type' => 'boolean'],
                            'worktree' => ['type' => 'string'],
                            'merge_commit' => ['type' => 'object', 'additionalProperties' => true],
                            'task_progress' => ['$ref' => '#/components/schemas/TaskProgress'],
                            'mockups' => ['$ref' => '#/components/schemas/MockupSummary'],
                            'feedback' => ['$ref' => '#/components/schemas/FeedbackSummary'],
                        ],
                        'required' => ['code', 'title', 'status'],
                    ],
                    'Task' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string', 'example' => 'TASK-01'],
                            'title' => ['type' => 'string'],
                            'type' => ['type' => 'string', 'example' => 'implementation'],
                            'status' => ['type' => 'string', 'enum' => ['TODO', 'DONE']],
                            'body' => ['type' => 'string'],
                            'dependencies' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                            'commit' => ['type' => 'object', 'additionalProperties' => true],
                        ],
                        'required' => ['id', 'title', 'status'],
                    ],
                    'PlanSummary' => [
                        'type' => 'object',
                        'properties' => [
                            'code' => ['type' => 'string'],
                            'plan_body' => ['type' => 'string'],
                            'updated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                        ],
                    ],
                    'BoardResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'metrics' => ['type' => 'object', 'additionalProperties' => true],
                            'status_order' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                            'columns' => [
                                'type' => 'object',
                                'additionalProperties' => [
                                    'type' => 'array',
                                    'items' => ['$ref' => '#/components/schemas/SpecSummary'],
                                ],
                            ],
                            'workflow' => [
                                'type' => 'object',
                                'additionalProperties' => ['type' => 'string'],
                                'description' => 'Workflow status key to label map',
                            ],
                        ],
                    ],
                    'SpecListResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'status' => ['type' => 'string', 'nullable' => true],
                            'count' => ['type' => 'integer'],
                            'items' => [
                                'type' => 'array',
                                'items' => ['$ref' => '#/components/schemas/SpecSummary'],
                            ],
                            'summary' => ['type' => 'object', 'additionalProperties' => true],
                        ],
                    ],
                    'SpecDetailResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'spec' => ['$ref' => '#/components/schemas/SpecSummary'],
                            'plan' => ['$ref' => '#/components/schemas/PlanSummary', 'nullable' => true],
                            'tasks' => [
                                'type' => 'array',
                                'items' => ['$ref' => '#/components/schemas/Task'],
                            ],
                            'workdir' => ['type' => 'string'],
                            'task_progress' => ['$ref' => '#/components/schemas/TaskProgress'],
                            'mockups' => ['$ref' => '#/components/schemas/MockupDetail', 'nullable' => true],
                            'feedback' => ['$ref' => '#/components/schemas/FeedbackDetail'],
                        ],
                    ],
                    'PrdHeading' => [
                        'type' => 'object',
                        'properties' => [
                            'level' => ['type' => 'integer'],
                            'title' => ['type' => 'string'],
                            'id' => ['type' => 'string'],
                        ],
                    ],
                    'PrdResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'content' => ['type' => 'string', 'description' => 'Full PRD markdown'],
                            'headings' => [
                                'type' => 'array',
                                'items' => ['$ref' => '#/components/schemas/PrdHeading'],
                            ],
                        ],
                        'required' => ['content', 'headings'],
                    ],
                ],
            ],
        ];
    }
}
