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
                'description' => 'JSON API for the Larapilot workflow board. '
                    .'Exposes backlog specs (user stories), plans, tasks, mockups, internal feedback, the PRD, and read-only diagnostics from `.larapilot/` artifacts. '
                    .'Read endpoints are available in the same environments where the `/larapilot` dashboard is browsable (never in production). '
                    .'POST `/specs/{code}/comments` appends internal feedback when comments are enabled.',
            ],
            'servers' => [
                ['url' => $baseUrl],
            ],
            'tags' => [
                ['name' => 'Board', 'description' => 'Kanban board overview'],
                ['name' => 'Specs', 'description' => 'User stories (backlog specs)'],
                ['name' => 'Feedback', 'description' => 'Internal PM/dev comments on user stories'],
                ['name' => 'PRD', 'description' => 'Product Requirements Document'],
                ['name' => 'Diagnostics', 'description' => 'Read-only runtime status and redacted log tail for bug triage'],
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
                '/specs/{code}/comments' => [
                    'post' => [
                        'tags' => ['Feedback'],
                        'summary' => 'Add internal feedback comment',
                        'description' => 'Appends a PM/dev comment to the spec\'s internal feedback log (`.larapilot/internal-feedback/{code}.md`). '
                            .'Comments are rejected when disabled globally, when the spec is DONE, or when author/message are missing.',
                        'operationId' => 'createSpecComment',
                        'parameters' => [
                            [
                                'name' => 'code',
                                'in' => 'path',
                                'required' => true,
                                'description' => 'Spec code (e.g. US-001)',
                                'schema' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9][A-Za-z0-9._-]*$'],
                            ],
                        ],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/CommentCreateRequest'],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => [
                                'description' => 'Comment appended',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/CommentCreateResponse'],
                                    ],
                                ],
                            ],
                            '404' => ['$ref' => '#/components/responses/NotFound'],
                            '422' => ['$ref' => '#/components/responses/UnprocessableEntity'],
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
                '/diagnostics' => [
                    'get' => [
                        'tags' => ['Diagnostics'],
                        'summary' => 'Runtime diagnostics snapshot',
                        'description' => 'Returns app status, health checks (storage, cache, database, queue, log file), and an optional redacted Laravel log tail for bug triage. '
                            .'Secrets in log lines are replaced with `[REDACTED]`. Available only where the dashboard is browsable and `larapilot.diagnostics.enabled` is true.',
                        'operationId' => 'getDiagnostics',
                        'parameters' => [
                            [
                                'name' => 'lines',
                                'in' => 'query',
                                'required' => false,
                                'description' => 'Max log lines to return (capped by config)',
                                'schema' => ['type' => 'integer', 'minimum' => 1, 'example' => 100],
                            ],
                            [
                                'name' => 'no_logs',
                                'in' => 'query',
                                'required' => false,
                                'description' => 'When true, skip the log tail and return status/checks only',
                                'schema' => ['type' => 'boolean', 'default' => false],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Diagnostics snapshot',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/DiagnosticsResponse'],
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
                    'UnprocessableEntity' => [
                        'description' => 'Validation or business rule failure',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => ['type' => 'string', 'example' => 'Comments are closed for this user story.'],
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
                            'screens' => [
                                'type' => 'array',
                                'items' => ['$ref' => '#/components/schemas/MockupScreen'],
                            ],
                        ],
                        'required' => ['available', 'screen_count', 'screens'],
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
                            'entries' => [
                                'type' => 'array',
                                'items' => ['$ref' => '#/components/schemas/FeedbackEntryDetail'],
                            ],
                        ],
                        'required' => ['enabled', 'entry_count', 'blocking_count', 'writable', 'path', 'entries'],
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
                    'FeedbackEntryDetail' => [
                        'type' => 'object',
                        'properties' => [
                            'at' => ['type' => 'string'],
                            'author' => ['type' => 'string'],
                            'status' => ['type' => 'string'],
                            'body' => ['type' => 'string'],
                            'body_html' => ['type' => 'string'],
                            'preview' => ['type' => 'string'],
                            'blocks_merge' => ['type' => 'boolean'],
                        ],
                        'required' => ['at', 'author', 'status', 'body', 'body_html', 'preview', 'blocks_merge'],
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
                                'items' => ['$ref' => '#/components/schemas/FeedbackEntryDetail'],
                            ],
                        ],
                        'required' => ['enabled', 'writable', 'path', 'entry_count', 'blocking_count', 'entries'],
                    ],
                    'CommentCreateRequest' => [
                        'type' => 'object',
                        'properties' => [
                            'author' => ['type' => 'string', 'maxLength' => 80, 'example' => 'PM'],
                            'message' => ['type' => 'string', 'maxLength' => 10000, 'example' => 'Please confirm Safari SSO scope.'],
                            'blocks_merge' => ['type' => 'boolean', 'default' => false],
                        ],
                        'required' => ['author', 'message'],
                    ],
                    'CommentCreateResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'code' => ['type' => 'string', 'example' => 'US-001'],
                            'path' => ['type' => 'string', 'example' => '.larapilot/internal-feedback/US-001.md'],
                            'entry_count' => ['type' => 'integer', 'minimum' => 0],
                            'blocking_count' => ['type' => 'integer', 'minimum' => 0],
                            'feedback' => ['$ref' => '#/components/schemas/FeedbackDetail'],
                            'mockups' => ['$ref' => '#/components/schemas/MockupDetail'],
                        ],
                        'required' => ['code', 'path', 'entry_count', 'blocking_count', 'feedback', 'mockups'],
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
                    'DiagnosticsCheck' => [
                        'type' => 'object',
                        'properties' => [
                            'ok' => ['type' => 'boolean'],
                            'detail' => ['type' => 'string'],
                        ],
                        'required' => ['ok', 'detail'],
                    ],
                    'DiagnosticsLogs' => [
                        'type' => 'object',
                        'properties' => [
                            'available' => ['type' => 'boolean'],
                            'path' => ['type' => 'string', 'nullable' => true],
                            'channel' => ['type' => 'string'],
                            'lines_requested' => ['type' => 'integer'],
                            'lines_returned' => ['type' => 'integer'],
                            'redacted' => ['type' => 'boolean'],
                            'entries' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                        ],
                        'required' => ['available', 'lines_requested', 'lines_returned', 'redacted', 'entries'],
                    ],
                    'DiagnosticsResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'collected_at' => ['type' => 'string', 'format' => 'date-time'],
                            'app' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'env' => ['type' => 'string'],
                                    'debug' => ['type' => 'boolean'],
                                    'url' => ['type' => 'string'],
                                    'timezone' => ['type' => 'string'],
                                    'locale' => ['type' => 'string'],
                                    'laravel_version' => ['type' => 'string'],
                                    'php_version' => ['type' => 'string'],
                                ],
                            ],
                            'checks' => [
                                'type' => 'object',
                                'additionalProperties' => ['$ref' => '#/components/schemas/DiagnosticsCheck'],
                            ],
                            'healthy' => ['type' => 'boolean'],
                            'logs' => ['$ref' => '#/components/schemas/DiagnosticsLogs'],
                        ],
                        'required' => ['collected_at', 'app', 'checks', 'healthy'],
                    ],
                ],
            ],
        ];
    }
}
