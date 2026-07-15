<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\Markdown;

class ApiService
{
    public function __construct(
        protected ConfigService $config,
        protected DashboardService $dashboard,
        protected SpecService $specs,
        protected PlanService $plans,
        protected PrdService $prd,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function board(): array
    {
        $board = $this->dashboard->board();

        return [
            'metrics' => $board['metrics'],
            'status_order' => $board['statusOrder'],
            'columns' => $board['columns'],
            'workflow' => $this->config->resolve()['workflow']['statuses'] ?? config('larapilot.workflow.statuses', []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function specs(?string $status = null): array
    {
        $result = $this->specs->list($status);

        $items = array_map(
            fn (array $spec): array => $this->enrichSpecSummary($spec),
            $result['items']
        );

        return [
            'status' => $status,
            'count' => count($items),
            'items' => $items,
            'summary' => $result['summary'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function spec(string $code): ?array
    {
        $data = $this->specs->show($code);

        if ($data === null) {
            return null;
        }

        $plan = $this->plans->read($code);

        return [
            'spec' => $data['spec'],
            'plan' => is_array($plan)
                ? [
                    'code' => (string) ($plan['code'] ?? $code),
                    'plan_body' => (string) ($plan['plan_body'] ?? ''),
                    'updated_at' => $plan['updated_at'] ?? null,
                ]
                : null,
            'tasks' => $data['tasks'],
            'workdir' => $data['workdir'],
            'task_progress' => $this->plans->taskProgress($code),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function prd(): ?array
    {
        $content = $this->prd->read();

        if ($content === null) {
            return null;
        }

        return [
            'content' => $content,
            'headings' => Markdown::headings($content),
        ];
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    protected function enrichSpecSummary(array $spec): array
    {
        $code = (string) ($spec['code'] ?? '');

        return array_merge($spec, [
            'task_progress' => $code !== '' ? $this->plans->taskProgress($code) : ['total' => 0, 'done' => 0],
        ]);
    }
}
