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
                    .'Exposes backlog specs (user stories), plans, tasks, mockups, internal feedback, the PRD, '
                    .'a Backstage catalog/TechDocs bundle, '
                    .'and read-only diagnostics from `.larapilot/` artifacts. '
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
                ['name' => 'Backstage', 'description' => 'Software-catalog entities, TechDocs metadata, and a delivery snapshot for backstage.io'],
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
                '/backstage' => [
                    'get' => [
                        'tags' => ['Backstage'],
                        'summary' => 'Backstage integration bundle',
                        'description' => 'Returns the Backstage software-catalog entities generated from this repository (Component plus an API entity per registered OpenAPI contract), '
                            .'the rendered `catalog-info.yaml`, TechDocs metadata, and a lean delivery snapshot (metrics, per-status counts, blocking feedback, story list). '
                            .'Intended for a Backstage entity provider or frontend plugin, proxied through the Backstage backend so the API token stays server-side.',
                        'operationId' => 'getBackstageBundle',
                        'responses' => [
                            '200' => [
                                'description' => 'Backstage bundle',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/BackstageResponse'],
                                    ],
                                ],
                            ],
                            '404' => ['$ref' => '#/components/responses/NotFound'],
                        ],
                    ],
                ],
                '/backstage/catalog-info.yaml' => [
                    'get' => [
                        'tags' => ['Backstage'],
                        'summary' => 'Backstage catalog descriptor',
                        'description' => 'Returns the same entities as a multi-document YAML catalog descriptor, ready to consume as a Backstage `url` location. '
                            .'Prefer committing the generated `catalog-info.yaml` to the repository: this endpoint is only browsable outside production.',
                        'operationId' => 'getBackstageCatalogInfo',
                        'responses' => [
                            '200' => [
                                'description' => 'Catalog descriptor',
                                'content' => [
                                    'application/yaml' => [
                                        'schema' => ['type' => 'string'],
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
                        'description' => 'Counts-only feedback summary for board cards and list items. Full entries are returned by the spec detail endpoint.',
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
                            'objective' => ['type' => 'string', 'nullable' => true],
                            'deadline' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
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
                            'assignee' => ['type' => 'string', 'nullable' => true, 'description' => 'Developer or persona executing the task'],
                            'estimate_hours' => ['type' => 'number', 'minimum' => 0, 'nullable' => true],
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
                    'BackstageEntity' => [
                        'type' => 'object',
                        'description' => 'A Backstage catalog entity (kind Component or API) in backstage.io/v1alpha1 form.',
                        'properties' => [
                            'apiVersion' => ['type' => 'string', 'example' => 'backstage.io/v1alpha1'],
                            'kind' => ['type' => 'string', 'enum' => ['Component', 'API']],
                            'metadata' => ['type' => 'object', 'additionalProperties' => true],
                            'spec' => ['type' => 'object', 'additionalProperties' => true],
                        ],
                        'required' => ['apiVersion', 'kind', 'metadata', 'spec'],
                    ],
                    'BackstageStory' => [
                        'type' => 'object',
                        'description' => 'Backlog entry reduced to what a Backstage card renders — no spec body or plan text.',
                        'properties' => [
                            'code' => ['type' => 'string', 'example' => 'US-001'],
                            'title' => ['type' => 'string'],
                            'status' => ['type' => 'string', 'example' => 'IN PROGRESS'],
                            'priority' => ['type' => 'string'],
                            'points' => ['type' => 'integer', 'minimum' => 0],
                            'epic' => ['$ref' => '#/components/schemas/Epic', 'nullable' => true],
                            'tasks' => ['$ref' => '#/components/schemas/TaskProgress'],
                            'blocking_feedback' => ['type' => 'integer', 'minimum' => 0],
                            'techdocs_path' => ['type' => 'string', 'nullable' => true, 'example' => 'backlog/US-001.md'],
                        ],
                        'required' => ['code', 'title', 'status', 'tasks', 'blocking_feedback'],
                    ],
                    'BackstageSnapshot' => [
                        'type' => 'object',
                        'properties' => [
                            'generated_at' => ['type' => 'string', 'format' => 'date-time'],
                            'entity_ref' => ['type' => 'string', 'example' => 'component:default/checkout'],
                            'component' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'namespace' => ['type' => 'string'],
                                    'title' => ['type' => 'string'],
                                    'lifecycle' => ['type' => 'string'],
                                    'owner' => ['type' => 'string'],
                                    'system' => ['type' => 'string', 'nullable' => true],
                                ],
                            ],
                            'metrics' => ['type' => 'object', 'additionalProperties' => true],
                            'status_order' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'counts_by_status' => ['type' => 'object', 'additionalProperties' => ['type' => 'integer']],
                            'blocking_feedback' => [
                                'type' => 'object',
                                'properties' => [
                                    'count' => ['type' => 'integer', 'minimum' => 0],
                                    'specs' => ['type' => 'array', 'items' => ['type' => 'string']],
                                ],
                            ],
                            'prd' => [
                                'type' => 'object',
                                'properties' => [
                                    'available' => ['type' => 'boolean'],
                                    'path' => ['type' => 'string'],
                                ],
                            ],
                            'stories' => [
                                'type' => 'array',
                                'items' => ['$ref' => '#/components/schemas/BackstageStory'],
                            ],
                            'links' => [
                                'type' => 'object',
                                'properties' => [
                                    'board' => ['type' => 'string', 'nullable' => true],
                                    'api' => ['type' => 'string', 'nullable' => true],
                                ],
                            ],
                        ],
                        'required' => ['generated_at', 'entity_ref', 'component', 'metrics', 'counts_by_status', 'stories'],
                    ],
                    'BackstageResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'generated_at' => ['type' => 'string', 'format' => 'date-time'],
                            'source' => ['type' => 'string', 'example' => 'larapilot'],
                            'version' => ['type' => 'string', 'example' => '2.2.0'],
                            'skill' => ['type' => 'string', 'example' => 'larapilot-backstage'],
                            'catalog' => [
                                'type' => 'object',
                                'properties' => [
                                    'path' => ['type' => 'string', 'example' => 'catalog-info.yaml'],
                                    'entity_refs' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'entities' => [
                                        'type' => 'array',
                                        'items' => ['$ref' => '#/components/schemas/BackstageEntity'],
                                    ],
                                    'yaml' => ['type' => 'string', 'description' => 'Rendered multi-document catalog-info.yaml'],
                                ],
                            ],
                            'techdocs' => [
                                'type' => 'object',
                                'properties' => [
                                    'enabled' => ['type' => 'boolean'],
                                    'docs_dir' => ['type' => 'string', 'example' => '.larapilot/techdocs'],
                                    'mkdocs_path' => ['type' => 'string', 'example' => 'mkdocs.yml'],
                                    'pages' => ['type' => 'array', 'items' => ['type' => 'string']],
                                ],
                            ],
                            'snapshot' => ['$ref' => '#/components/schemas/BackstageSnapshot'],
                            'instructions' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'required' => ['generated_at', 'source', 'version', 'catalog', 'techdocs', 'snapshot', 'instructions'],
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
