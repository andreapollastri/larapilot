<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\AtomicFile;
use Larapilot\Support\Checklist;
use Larapilot\Support\FileLock;
use Larapilot\Support\SpecCode;
use Symfony\Component\Yaml\Yaml;

class PlanService
{
    /**
     * @var array<string, array<string, mixed>|null>
     */
    protected array $planCache = [];

    public function __construct(
        protected ConfigService $config,
        protected SpecService $specs,
        protected GitService $git,
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

        $this->planCache[$code] = $plan;

        $this->specs->setStatus($code, $this->config->status('planned'));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function read(string $code): ?array
    {
        if (array_key_exists($code, $this->planCache)) {
            return $this->planCache[$code];
        }

        return $this->planCache[$code] = $this->readFromDisk($code);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function readFromDisk(string $code): ?array
    {
        $path = $this->path($code);

        if (! is_file($path)) {
            return null;
        }

        $parsed = Yaml::parseFile($path);

        return is_array($parsed) ? $parsed : null;
    }

    /**
     * @return array{total: int, done: int}
     */
    public function taskProgress(string $code): array
    {
        $plan = $this->read($code);

        if ($plan === null) {
            return ['total' => 0, 'done' => 0];
        }

        $tasks = is_array($plan['tasks'] ?? null) ? $plan['tasks'] : [];
        $done = 0;

        foreach ($tasks as $task) {
            if (! is_array($task)) {
                continue;
            }

            if (strtoupper((string) ($task['status'] ?? '')) === 'DONE') {
                $done++;
            }
        }

        return [
            'total' => count($tasks),
            'done' => $done,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function metrics(): array
    {
        $totalTasks = 0;
        $doneTasks = 0;
        $specsWithPlans = 0;

        foreach ($this->specs->allSpecs() as $spec) {
            $code = (string) ($spec['code'] ?? '');

            if ($code === '') {
                continue;
            }

            $progress = $this->taskProgress($code);

            if ($progress['total'] > 0) {
                $specsWithPlans++;
            }

            $totalTasks += $progress['total'];
            $doneTasks += $progress['done'];
        }

        return [
            'total_tasks' => $totalTasks,
            'done_tasks' => $doneTasks,
            'task_completion_rate' => $totalTasks > 0 ? round($doneTasks / $totalTasks * 100, 1) : 0.0,
            'specs_with_plans' => $specsWithPlans,
        ];
    }

    /**
     * Write the tasks of a plan back as a re-plan leaves them — hours,
     * assignees, dependencies. The spec keeps the status it has.
     *
     * @param  list<array<string, mixed>>  $tasks
     */
    public function replaceTasks(string $code, array $tasks): void
    {
        FileLock::withLock($this->path($code), function () use ($code, $tasks): void {
            unset($this->planCache[$code]);
            $plan = $this->read($code);

            if ($plan === null) {
                throw new \RuntimeException("Plan for {$code} not found.");
            }

            $plan['tasks'] = array_values($tasks);
            $plan['updated_at'] = now()->toIso8601String();

            AtomicFile::write(
                $this->path($code),
                Yaml::dump($plan, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
            );

            $this->planCache[$code] = $plan;
        });
    }

    /**
     * @return array{sha: string, short_sha: string, subject: string, committed_at: string, url: string|null}|null
     */
    public function markTaskDone(string $code, string $taskId, ?string $commitSha = null): ?array
    {
        return FileLock::withLock($this->path($code), function () use ($code, $taskId, $commitSha): ?array {
            unset($this->planCache[$code]);
            $plan = $this->read($code);

            if ($plan === null) {
                throw new \RuntimeException("Plan for {$code} not found.");
            }

            $tasks = $plan['tasks'] ?? [];
            $found = false;
            $commit = $this->taskGit($tasks, $taskId)->resolveTaskCommit($code, $taskId, $commitSha);

            foreach ($tasks as $index => $task) {
                if (($task['id'] ?? null) === $taskId) {
                    $tasks[$index]['status'] = 'DONE';
                    if (is_string($task['body'] ?? null)) {
                        $tasks[$index]['body'] = Checklist::tick($task['body']);
                    }

                    if ($commit !== null) {
                        $tasks[$index]['commit'] = $commit;
                    } else {
                        unset($tasks[$index]['commit']);
                    }

                    $found = true;
                    break;
                }
            }

            if (! $found) {
                throw new \RuntimeException("Task {$taskId} not found in plan for {$code}.");
            }

            $plan['tasks'] = $tasks;
            $plan['updated_at'] = now()->toIso8601String();

            AtomicFile::write(
                $this->path($code),
                Yaml::dump($plan, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
            );

            $this->planCache[$code] = $plan;

            return $commit;
        });
    }

    /**
     * A `repo: frontend` task is committed in the linked frontend repository
     * — or in its project's own repository, cloned inside the workspace — so
     * its commit is looked for there.
     *
     * @param  array<int, mixed>  $tasks
     */
    protected function taskGit(array $tasks, string $taskId): GitService
    {
        foreach ($tasks as $task) {
            if (! is_array($task) || ($task['id'] ?? null) !== $taskId) {
                continue;
            }

            if (strtolower((string) ($task['repo'] ?? '')) === 'frontend') {
                $root = app(FrontendService::class)->gitRootFor(is_string($task['project'] ?? null) ? $task['project'] : null);

                if ($root !== null) {
                    return $this->git->at($root);
                }
            }

            break;
        }

        return $this->git;
    }
}
