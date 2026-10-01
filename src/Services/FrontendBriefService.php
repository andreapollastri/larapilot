<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Services\Frontend\RepoFiles;
use Larapilot\Support\AtomicFile;

/**
 * The brief the frontend team builds from when `frontend.mode` is
 * `handoff`: the story, the frontend tasks of its plan, the slice of the
 * API contract they call, the mockups, and how to hand the work back.
 *
 * It is read in a session opened in the frontend repository, where that
 * team's own agent rules load by themselves. The frame is English, like the
 * developer docs; the story and the tasks stay in the language they were
 * written in.
 */
class FrontendBriefService
{
    public function __construct(
        protected ConfigService $config,
        protected SpecService $specs,
        protected PlanService $plans,
        protected FrontendService $frontend,
        protected CompanionService $companion,
    ) {}

    public function directory(): string
    {
        return rtrim($this->config->setupInfo()['paths']['frontend_briefs'], '/');
    }

    /**
     * @param  list<string>  $taskIds  Limit the brief to these tasks.
     * @return array<string, mixed>
     *
     * @throws \RuntimeException When the spec, its plan, or its frontend tasks are missing.
     */
    public function build(string $code, array $taskIds = [], bool $write = true): array
    {
        $spec = $this->specs->find($code);

        if ($spec === null) {
            throw new \RuntimeException("Spec {$code} not found.");
        }

        $plan = $this->plans->read($code);

        if ($plan === null) {
            throw new \RuntimeException("Spec {$code} has no plan yet: run /larapilot-plan first.");
        }

        $tasks = array_values(array_filter(
            is_array($plan['tasks'] ?? null) ? $plan['tasks'] : [],
            static fn (mixed $task): bool => is_array($task)
        ));

        $frontendTasks = array_values(array_filter($tasks, static function (array $task) use ($taskIds): bool {
            if (strtolower((string) ($task['repo'] ?? '')) !== 'frontend') {
                return false;
            }

            return $taskIds === [] || in_array((string) ($task['id'] ?? ''), $taskIds, true);
        }));

        if ($frontendTasks === []) {
            throw new \RuntimeException($taskIds === []
                ? "The plan of {$code} has no task with repo: frontend."
                : "None of the tasks named is a repo: frontend task of {$code}.");
        }

        $byId = [];

        foreach ($tasks as $task) {
            $byId[(string) ($task['id'] ?? '')] = $task;
        }

        $scan = $this->frontend->configured() ? $this->frontend->scan(null, null, false) : null;
        $content = $this->render($code, $spec, $plan, $frontendTasks, $byId, is_array($scan) && ($scan['ok'] ?? false) === true ? $scan : null);
        $path = $this->directory().'/'.$code.'.md';

        if ($write) {
            if (! is_dir($this->directory())) {
                mkdir($this->directory(), 0755, true);
            }

            AtomicFile::write($path, $content);
        }

        return [
            'code' => $code,
            'path' => $write ? $this->config->relativePath($path) : null,
            'tasks' => array_map(static fn (array $task): string => (string) ($task['id'] ?? ''), $frontendTasks),
            'bytes' => strlen($content),
            'content' => $write ? null : $content,
            'prompt' => sprintf(
                'In the frontend repository: "Build %s from the Larapilot brief at <Laravel checkout>/%s. Our own agent rules win on code; the API contract in the brief does not change. Commit each task with %s TASK-NN in the subject."',
                $code,
                $this->config->relativePath($path),
                $code
            ),
            'hand_back' => sprintf('php artisan larapilot:task-done %s TASK-NN (finds the frontend commit by its subject)', $code),
        ];
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<string, mixed>  $plan
     * @param  list<array<string, mixed>>  $frontendTasks
     * @param  array<string, array<string, mixed>>  $byId
     * @param  array<string, mixed>|null  $scan
     */
    protected function render(string $code, array $spec, array $plan, array $frontendTasks, array $byId, ?array $scan): string
    {
        $title = trim((string) ($spec['title'] ?? $code));
        $lines = [];
        $lines[] = "# Frontend brief — {$code} · {$title}";
        $lines[] = '';
        $lines[] = sprintf('> Written by Larapilot on %s from the Laravel workspace. Built by the frontend team in its own repository, under its own rules. Regenerate it with `php artisan larapilot:frontend-brief %s` after the plan changes.', date('Y-m-d'), $code);
        $lines[] = '';
        $lines[] = '## How to work this brief';
        $lines[] = '';
        $lines[] = '1. Open the frontend repository: its AGENTS.md, CLAUDE.md, and editor rules load there and **win on how the code is written**.';
        $lines[] = '2. **Do not change the API contract below.** When the UI needs something the API does not give, stop and ask the backend team — never call an undocumented endpoint, never invent a field.';
        $lines[] = '3. Build the tasks in order of their dependencies. A task that waits for a backend task waits until that one is done.';
        $pattern = is_string($scan['git']['commits']['pattern'] ?? null)
            ? str_replace('{code}', $code, (string) $scan['git']['commits']['pattern'])
            : $code.' TASK-NN <summary>';
        $lines[] = sprintf('4. Commit each task with `%s TASK-NN` in the subject, the way your history writes them: `%s`.', $code, $pattern);
        $lines[] = sprintf('5. Tell the backend team when a task is done. In the Laravel workspace, `php artisan larapilot:task-done %s TASK-NN` finds the frontend commit by its subject.', $code);

        if ($scan !== null) {
            $lines = array_merge($lines, $this->workspaceSection($scan));
        }

        $lines[] = '';
        $lines[] = '## The story';
        $lines[] = '';
        $lines[] = trim((string) ($spec['body'] ?? '')) !== '' ? trim((string) $spec['body']) : '_The spec has no body._';
        $lines[] = '';
        $lines[] = '## Frontend tasks';

        foreach ($frontendTasks as $task) {
            $id = (string) ($task['id'] ?? '');
            $dependencies = array_values(array_filter(is_array($task['dependencies'] ?? null) ? $task['dependencies'] : [], 'is_string'));
            $lines[] = '';
            $lines[] = sprintf('### %s — %s', $id, trim((string) ($task['title'] ?? '')));
            $lines[] = '';
            $facts = ['Status: '.(string) ($task['status'] ?? 'TODO')];

            if (is_string($task['project'] ?? null) && $task['project'] !== '') {
                $facts[] = 'Project: `'.$task['project'].'`';
            }

            if (is_array($task['shared'] ?? null) && $task['shared'] !== []) {
                $facts[] = 'Shared libraries it changes: '.implode(', ', array_map(static fn (mixed $name): string => '`'.(string) $name.'`', $task['shared']));
            }

            if ($dependencies !== []) {
                $facts[] = 'Waits for: '.implode(', ', array_map(function (string $dependency) use ($byId): string {
                    $other = $byId[$dependency] ?? [];
                    $where = strtolower((string) ($other['repo'] ?? '')) === 'frontend' ? 'frontend' : 'backend';

                    return sprintf('%s (%s, %s)', $dependency, $where, (string) ($other['status'] ?? '?'));
                }, $dependencies));
            }

            $lines[] = implode(' · ', $facts);
            $lines[] = '';
            $lines[] = $this->demoteHeadings(trim((string) ($task['body'] ?? '')));
        }

        $lines = array_merge($lines, $this->contractSection($spec, $plan, $frontendTasks, $scan));
        $lines = array_merge($lines, $this->mockupSection($code));

        $backend = array_values(array_filter($byId, static fn (array $task): bool => strtolower((string) ($task['repo'] ?? '')) !== 'frontend' && ($task['id'] ?? '') !== ''));

        if ($backend !== []) {
            $lines[] = '';
            $lines[] = '## Backend tasks of the same spec';
            $lines[] = '';

            foreach ($backend as $task) {
                $lines[] = sprintf('- %s — %s (%s)', (string) $task['id'], trim((string) ($task['title'] ?? '')), (string) ($task['status'] ?? 'TODO'));
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<string, mixed>  $scan
     * @return list<string>
     */
    protected function workspaceSection(array $scan): array
    {
        $lines = ['', '## Where it goes', ''];
        $workspace = $scan['workspace'] ?? [];

        if (array_key_exists('run_in', $scan) && $scan['run_in'] === null) {
            $lines[] = '- This project builds inside the monorepo it belongs to: run its commands there.';
        }

        $lines[] = sprintf('- Workspace: %s%s, %s.', (string) ($workspace['kind'] ?? 'single'), isset($workspace['tool']['version']) ? ' '.$workspace['tool']['version'] : '', (string) ($workspace['package_manager']['name'] ?? 'npm'));

        foreach ($scan['target_projects'] ?? [] as $project) {
            $framework = isset($project['framework']['version']) ? ' '.$project['framework']['version'] : '';
            $lines[] = sprintf('- Project `%s` (`%s`) — %s%s.', $project['name'], $project['root'], (string) ($project['stack'] ?? 'unknown stack'), $framework);

            foreach ($project['commands'] ?? [] as $step => $command) {
                if ($step !== 'serve') {
                    $lines[] = sprintf('  - %s: `%s`', $step, $command);
                }
            }
        }

        if (isset($scan['commands']['affected'])) {
            $lines[] = sprintf('- Before each commit: `%s`', $scan['commands']['affected']);
        }

        foreach ($scan['write_scope']['shared'] ?? [] as $shared) {
            $lines[] = sprintf('- Shared library `%s` (`%s`) is used by %d other application(s): change it only when a task names it.', $shared['name'], $shared['root'], $shared['used_by']);
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<string, mixed>  $plan
     * @param  list<array<string, mixed>>  $frontendTasks
     * @param  array<string, mixed>|null  $scan
     * @return list<string>
     */
    protected function contractSection(array $spec, array $plan, array $frontendTasks, ?array $scan): array
    {
        $lines = ['', '## API contract', ''];
        $openApi = $this->companion->productOpenApiPath();

        if ($openApi === null) {
            $lines[] = '- The Laravel workspace publishes no OpenAPI document yet: the endpoints are described in the tasks above. Ask the backend team before calling anything else.';

            return $lines;
        }

        $lines[] = sprintf('- OpenAPI document: `%s` in the Laravel repository.', $this->config->relativePath($openApi));
        $text = implode("\n", array_merge(
            [(string) ($spec['body'] ?? ''), (string) ($plan['plan_body'] ?? '')],
            array_map(static fn (array $task): string => (string) ($task['body'] ?? '').' '.(string) ($task['title'] ?? ''), $frontendTasks)
        ));

        $operations = $this->operations(RepoFiles::json($openApi) ?? [], $text);

        if ($operations !== []) {
            $lines[] = '- Operations this spec calls:';

            foreach ($operations as $operation) {
                $lines[] = '  - '.$operation;
            }
        } else {
            $lines[] = '- The tasks name no path of the document: read the operations they describe there.';
        }

        foreach ($scan['api_client']['regenerate'] ?? [] as $command) {
            $lines[] = sprintf('- The client is generated: regenerate it with `%s` against that document — never edit the generated files.', $command);
        }

        return $lines;
    }

    /**
     * The operations of the document whose path the spec or its tasks name.
     *
     * @param  array<string, mixed>  $document
     * @return list<string>
     */
    protected function operations(array $document, string $text): array
    {
        $paths = is_array($document['paths'] ?? null) ? $document['paths'] : [];
        $found = [];

        foreach ($paths as $path => $methods) {
            if (! is_string($path) || ! is_array($methods)) {
                continue;
            }

            // `/api/orders/{order}` also matches `/api/orders/42`, and a task
            // may leave out the `/api` prefix the document carries.
            $patterns = [$this->pathPattern($path)];

            if (str_starts_with($path, '/api/')) {
                $patterns[] = $this->pathPattern(substr($path, 4));
            }

            $mentioned = false;

            foreach ($patterns as $pattern) {
                $mentioned = $mentioned || preg_match($pattern, $text) === 1;
            }

            foreach ($methods as $method => $operation) {
                if (! in_array(strtolower((string) $method), ['get', 'post', 'put', 'patch', 'delete'], true) || ! is_array($operation)) {
                    continue;
                }

                $operationId = is_string($operation['operationId'] ?? null) ? $operation['operationId'] : null;

                if (! $mentioned && ($operationId === null || preg_match('/\b'.preg_quote($operationId, '/').'\b/', $text) !== 1)) {
                    continue;
                }

                $summary = is_string($operation['summary'] ?? null) ? ' — '.$operation['summary'] : '';
                $found[] = sprintf('`%s %s`%s', strtoupper((string) $method), $path, $summary);
            }
        }

        return array_slice(array_values(array_unique($found)), 0, 40);
    }

    protected function pathPattern(string $path): string
    {
        $parts = preg_split('/(\{[^}]+\})/', $path, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$path];
        $regex = '';

        foreach ($parts as $part) {
            $regex .= str_starts_with($part, '{') ? '[^\s\/`\'")]+' : preg_quote($part, '#');
        }

        return '#(?<![\w\/])'.$regex.'(?![\w\/])#';
    }

    /**
     * @return list<string>
     */
    protected function mockupSection(string $code): array
    {
        $directory = rtrim($this->config->setupInfo()['paths']['mockups'], '/').'/'.$code;

        if (! is_dir($directory)) {
            return [];
        }

        $files = RepoFiles::walk($directory, static fn (string $path): bool => preg_match('/\.(html|png|jpe?g|svg|webp|md)$/i', $path) === 1, 3)['files'];

        if ($files === []) {
            return [];
        }

        $lines = ['', '## Mockups', '', 'In the Laravel repository — the visual contract the UI matches:', ''];

        foreach (array_slice($files, 0, 30) as $file) {
            $lines[] = '- `'.$this->config->relativePath($directory.'/'.$file).'`';
        }

        return $lines;
    }

    /**
     * Task bodies use `##` headings; inside the brief they sit under a `###`.
     */
    protected function demoteHeadings(string $body): string
    {
        return (string) preg_replace_callback('/^(#{1,4})(\s)/m', static fn (array $matches): string => '#'.$matches[1].'#'.$matches[2], $body);
    }
}
