<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\Markdown;

class DashboardService
{
    public function __construct(
        protected ConfigService $config,
        protected SpecService $specs,
        protected PlanService $plans,
        protected PrdService $prd,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function board(): array
    {
        $workflow = $this->config->resolve()['workflow']['statuses'] ?? config('larapilot.workflow.statuses', []);

        $columns = [];

        foreach (array_values(is_array($workflow) ? $workflow : []) as $status) {
            $columns[(string) $status] = [];
        }

        foreach ($this->specs->allSpecs() as $spec) {
            $status = (string) ($spec['status'] ?? 'TODO');

            if (! array_key_exists($status, $columns)) {
                $columns[$status] = [];
            }

            $columns[$status][] = $spec;
        }

        return [
            'metrics' => $this->specs->metrics(),
            'columns' => $columns,
            // include columns created for statuses outside the configured
            // workflow, so those specs still show on the board
            'statusOrder' => array_map('strval', array_keys($columns)),
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
            'html' => Markdown::toHtml($content),
            'headings' => Markdown::headings($content),
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
        $tasks = [];

        foreach ($data['tasks'] as $task) {
            if (! is_array($task)) {
                continue;
            }

            $tasks[] = array_merge($task, [
                'body_html' => Markdown::toHtml((string) ($task['body'] ?? '')),
            ]);
        }

        return [
            'spec' => $data['spec'],
            'tasks' => $tasks,
            'workdir' => $data['workdir'],
            'spec_html' => Markdown::toHtml((string) ($data['spec']['body'] ?? '')),
            'plan_html' => is_array($plan)
                ? Markdown::toHtml((string) ($plan['plan_body'] ?? ''))
                : null,
        ];
    }
}
