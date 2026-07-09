<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\AtomicFile;
use Larapilot\Support\Checklist;
use Larapilot\Support\SpecCode;
use Symfony\Component\Yaml\Yaml;

class PlanService
{
    public function __construct(
        protected ConfigService $config,
        protected SpecService $specs,
    ) {}

    public function path(string $code): string
    {
        $config = $this->config->resolve();
        $planning = $this->config->absolutePath($config['file']['planning'] ?? '.larapilot/plans/');

        return rtrim($planning, '/').'/'.SpecCode::ensure($code).'-plan.yaml';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function save(string $code, array $payload): void
    {
        $plan = [
            'code' => $code,
            'plan_body' => (string) ($payload['plan_body'] ?? ''),
            'tasks' => is_array($payload['tasks'] ?? null) ? $payload['tasks'] : [],
            'updated_at' => now()->toIso8601String(),
        ];

        AtomicFile::write(
            $this->path($code),
            Yaml::dump($plan, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
        );

        $this->specs->setStatus($code, $this->config->status('planned'));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function read(string $code): ?array
    {
        $path = $this->path($code);

        if (! is_file($path)) {
            return null;
        }

        $parsed = Yaml::parseFile($path);

        return is_array($parsed) ? $parsed : null;
    }

    public function markTaskDone(string $code, string $taskId): void
    {
        $plan = $this->read($code);

        if ($plan === null) {
            throw new \RuntimeException("Plan for {$code} not found.");
        }

        $tasks = $plan['tasks'] ?? [];
        $found = false;

        foreach ($tasks as $index => $task) {
            if (($task['id'] ?? null) === $taskId) {
                $tasks[$index]['status'] = 'DONE';
                if (is_string($task['body'] ?? null)) {
                    $tasks[$index]['body'] = Checklist::tick($task['body']);
                }
                $found = true;
                break;
            }
        }

        if (! $found) {
            throw new \RuntimeException("Task {$taskId} not found in plan for {$code}.");
        }

        $plan['tasks'] = $tasks;

        AtomicFile::write(
            $this->path($code),
            Yaml::dump($plan, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
        );
    }
}
