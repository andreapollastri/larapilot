<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Services\Frontend\AgentRules;
use Larapilot\Services\Frontend\ApiClientDetector;
use Larapilot\Services\Frontend\CommandResolver;
use Larapilot\Services\Frontend\CommitStyle;
use Larapilot\Services\Frontend\NxGraph;
use Larapilot\Services\Frontend\ProjectProfiler;
use Larapilot\Services\Frontend\RepoFiles;
use Larapilot\Services\Frontend\RepoGit;
use Larapilot\Services\Frontend\RepoIndex;
use Larapilot\Services\Frontend\WorkspaceInspector;
use Larapilot\Services\Frontend\WorkspaceLocator;

/**
 * The external frontend repository, seen from the Laravel workspace: which
 * kind of workspace it is, which of its projects belong to this product,
 * the rules its team wrote for agents, how its code is written, and the
 * commands that verify it.
 */
class FrontendService
{
    /**
     * Projects listed by a compact scan; `--full` lists them all.
     */
    public const COMPACT_PROJECTS = 60;

    public const MODES = ['driven', 'handoff'];

    public function __construct(
        protected ConfigService $config,
        protected CompanionService $companion,
    ) {}

    /**
     * @return array{repo_path: string|null, stack: string|null, projects: list<string>, mode: string, configured: bool}
     */
    public function info(): array
    {
        return $this->config->frontend();
    }

    public function configured(): bool
    {
        $path = $this->config->frontendRepoPath();

        return is_string($path) && $path !== '' && is_dir($path);
    }

    /**
     * @return array{valid: bool, path: string, errors: list<string>}
     */
    public function validatePath(string $path): array
    {
        $normalized = rtrim(trim($path), '/\\');
        $errors = [];

        if ($normalized === '') {
            $errors[] = 'Path is empty.';
        } elseif (! is_dir($normalized)) {
            $errors[] = 'Directory does not exist or is not readable.';
        } elseif (! is_readable($normalized)) {
            $errors[] = 'Directory is not readable.';
        }

        return [
            'valid' => $errors === [],
            'path' => $normalized,
            'errors' => $errors,
        ];
    }

    public function cachePath(): string
    {
        return $this->config->absolutePath('.larapilot/cache/frontend');
    }

    /**
     * Everything an agent needs before planning or writing frontend code.
     *
     * @param  list<string>|null  $projects  Target projects; null takes the configured ones.
     * @return array<string, mixed>
     */
    public function scan(?string $path = null, ?array $projects = null, bool $useCli = true, bool $fresh = false, bool $full = false): array
    {
        $resolved = $this->resolveRoot($path);

        if (isset($resolved['error'])) {
            return $resolved['error'];
        }

        $repository = $resolved['root'];
        $frontend = $this->config->frontend();
        $location = WorkspaceLocator::locate($repository, $resolved['configured'] ? $frontend['workspace_path'] : null);
        $root = $location['root'];
        $index = RepoIndex::build($root);
        $inspector = new WorkspaceInspector($root, $index, new NxGraph($this->cachePath()));
        $workspace = $inspector->inspect($useCli, $fresh);
        $workspaceGit = RepoGit::toplevel($root);
        $base = RepoGit::defaultBase($workspaceGit ?? $root, $workspace['nx']['default_base'] ?? null);

        $requested = $projects ?? ($resolved['configured'] ? $frontend['projects'] : []);

        // A project cloned inside its workspace is the target by itself.
        if ($requested === [] && $location['project_root'] !== null) {
            $requested = [$location['project_root']];
        }

        $targets = $this->targets($workspace, $requested);
        $rootPackage = RepoFiles::json($root.'/package.json');
        $rootDependencies = WorkspaceInspector::dependencies($rootPackage);

        $commands = new CommandResolver($workspace, $base, $index);
        $profiler = new ProjectProfiler($root, $inspector);
        $targetProjects = [];
        $sample = [];

        foreach ($targets['resolved'] as $name) {
            $project = $workspace['projects'][$name];

            if ($project['stack'] === null) {
                $project['stack'] = $inspector->stack($project, $rootDependencies, true);
                $workspace['projects'][$name]['stack'] = $project['stack'];
            }

            $own = $project['root'] === '.' ? [] : WorkspaceInspector::dependencies(RepoFiles::json($root.'/'.$project['root'].'/package.json'));
            $profile = $profiler->profile($project, $own + $rootDependencies);
            $package = WorkspaceInspector::FRAMEWORK_PACKAGES[$project['stack'] ?? ''] ?? null;
            $version = $package !== null ? $inspector->version($package, (string) $project['root']) : null;
            $sample = array_merge($sample, $inspector->sourceFiles($project, 600));
            $projectCommands = $commands->forProject($project);
            $projectGit = RepoGit::toplevel($index->absolute((string) $project['root']));

            $targetProjects[] = array_filter([
                'name' => $name,
                'root' => $project['root'],
                'git_root' => $projectGit !== null && $projectGit !== ($workspaceGit ?? $root) ? $projectGit : null,
                'source_root' => $project['source_root'],
                'type' => $project['type'],
                'tags' => $project['tags'] === [] ? null : $project['tags'],
                'prefix' => $project['prefix'] ?? null,
                'stack' => $project['stack'],
                'framework' => $package === null ? null : [
                    'package' => $package,
                    'version' => $version,
                    'major' => WorkspaceInspector::major($version),
                ],
                'targets' => array_keys($project['targets']),
                'depends_on' => $workspace['dependencies'][$name] ?? [],
                'commands' => $projectCommands,
                'unavailable' => $project['unavailable'] === [] ? null : $project['unavailable'],
                'tests' => $profile['tests'] + ['runnable' => isset($projectCommands['test'])],
                'libraries' => $profile['libraries'] === [] ? null : $profile['libraries'],
                'observed' => $profile['observed'],
                'exemplars' => $profile['exemplars'],
                'entrypoints' => $this->entrypoints($root, $project),
            ], static fn (mixed $value): bool => $value !== null);
        }

        $scope = $this->writeScope($workspace, $targets['resolved']);
        $scope['vendored'] = $this->vendored($index, $scope['owned']);
        $rules = (new AgentRules($index))->forProjects(array_values(array_unique(array_merge(
            array_map(static fn (array $project): string => (string) $project['root'], $targetProjects),
            array_map(static fn (array $owned): string => (string) $owned['root'], $scope['owned'])
        ))));
        $enclosing = $location['source'] === 'repository' ? WorkspaceLocator::enclosing($repository) : null;
        $inherited = $enclosing !== null ? AgentRules::inherited($enclosing, $repository) : null;

        if ($inherited !== null) {
            $rules = ['inherited' => $inherited] + $rules;
        }

        $api = (new ApiClientDetector($root, $workspace))->detect(
            array_map(static fn (string $name): array => $workspace['projects'][$name], $targets['resolved']),
            $sample
        );
        $openApi = $this->companion->productOpenApiPath();
        $api['product_openapi'] = $openApi !== null ? $this->config->relativePath($openApi) : null;

        $stacks = array_values(array_unique(array_filter(array_map(
            static fn (array $project): ?string => $project['stack'] ?? null,
            $targetProjects
        ))));

        $detected = $stacks[0] ?? WorkspaceInspector::frameworkFrom($rootDependencies);

        // Where the frontend commits go: the project's own repository when it
        // is one inside the workspace, the repository linked otherwise.
        $commitRoot = $targetProjects[0]['git_root'] ?? (RepoGit::toplevel($repository) ?? $repository);
        $commitBase = $commitRoot === ($workspaceGit ?? $root) ? $base : RepoGit::defaultBase($commitRoot, null);
        $workspaceCommands = $commands->forWorkspace(array_map(static fn (string $name): array => $workspace['projects'][$name], $targets['resolved']));
        $nested = array_values(array_filter($targetProjects, static fn (array $project): bool => isset($project['git_root'])));

        // The workspace's git never sees the commits of a repository nested
        // in it, so `affected` would find nothing to run.
        if ($nested !== [] && isset($workspaceCommands['affected'])) {
            unset($workspaceCommands['affected']);
        }

        return [
            'ok' => true,
            'path' => $repository,
            // Every path below is relative to `root`, and the commands run
            // there; `run_in` is null while the workspace is out of reach.
            'root' => $root,
            'run_in' => $location['source'] === 'missing' ? null : $root,
            'mode' => $frontend['mode'],
            'git' => array_filter([
                'repository' => RepoGit::isRepository($commitRoot),
                'root' => $commitRoot,
                'branch' => RepoGit::branch($commitRoot),
                'default_base' => $commitBase,
                'commits' => CommitStyle::read($commitRoot),
            ], static fn (mixed $value): bool => $value !== null),
            'workspace' => array_filter([
                'location' => $location['detached'] ? array_filter([
                    'source' => $location['source'],
                    'project_root' => $location['project_root'],
                    'expected_root' => $location['expected_root'],
                    'signals' => $location['signals'],
                ], static fn (mixed $value): bool => $value !== null) : null,
                'enclosing' => $enclosing,
                'kind' => $workspace['kind'],
                'monorepo' => $workspace['monorepo'],
                'tool' => $workspace['tool'],
                'package_manager' => $workspace['package_manager'],
                'node' => $workspace['node']['version'] !== null ? $workspace['node'] : null,
                'graph' => $workspace['graph'],
                'nx_plugins' => $workspace['nx']['plugins'] ?? null,
                'projects_total' => count($workspace['projects']),
                'applications' => count(array_filter($workspace['projects'], static fn (array $project): bool => $project['type'] === 'application')),
                'libraries' => count(array_filter($workspace['projects'], static fn (array $project): bool => $project['type'] === 'library')),
            ], static fn (mixed $value): bool => $value !== null),
            'targets' => $targets['summary'],
            'target_projects' => $targetProjects,
            'write_scope' => $scope,
            'rules' => $rules,
            'commands' => $workspaceCommands,
            'generators' => $commands->generators(array_map(static fn (string $name): array => $workspace['projects'][$name], $targets['resolved'])),
            'api_client' => $api,
            'playbooks' => $this->playbooks($stacks),
            'projects' => $this->projectList($workspace, $full),
            'warnings' => array_merge(
                $this->warnings($workspace, $targets, $rules, $frontend['mode'], $location, $targetProjects),
                array_map(static fn (array $project): string => sprintf(
                    '%s is a git repository of its own inside the workspace: commit there (git_root), and run its targets and those of the projects that depend on it — the workspace\'s affected cannot see its changes.',
                    $project['name']
                ), $nested)
            ),
            // Fields of the first scan, kept for agents and scripts that read them.
            'package' => is_array($rootPackage) ? [
                'name' => $rootPackage['name'] ?? null,
                'private' => $rootPackage['private'] ?? null,
            ] : null,
            'stack' => [
                'configured' => $frontend['stack'],
                'detected' => $detected,
                'resolved' => $frontend['stack'] ?? $detected,
            ],
            'tooling' => [
                'nx' => $workspace['kind'] === 'nx',
                'vite' => RepoFiles::firstExisting($root, RepoFiles::configNames('vite.config')) !== null,
                'next' => RepoFiles::firstExisting($root, RepoFiles::configNames('next.config')) !== null,
                'nuxt' => RepoFiles::firstExisting($root, RepoFiles::configNames('nuxt.config')) !== null,
                'angular' => is_file($root.'/angular.json') || in_array('Angular', $stacks, true),
            ],
            'structure' => [
                'src' => is_dir($root.'/src'),
                'app' => is_dir($root.'/app'),
                'apps' => is_dir($root.'/apps'),
                'libs' => is_dir($root.'/libs'),
                'packages' => is_dir($root.'/packages'),
                'pages' => is_dir($root.'/pages') || is_dir($root.'/src/pages'),
                'components' => is_dir($root.'/components') || is_dir($root.'/src/components'),
                'larapilot_docs' => is_dir($root.'/.larapilot/docs'),
            ],
            'entrypoints' => array_values(array_unique(array_merge([], ...array_map(
                static fn (array $project): array => $project['entrypoints'] ?? [],
                $targetProjects
            )))),
            'dependencies' => array_values(array_intersect(
                array_keys($rootDependencies),
                ['react', 'react-dom', 'vue', '@angular/core', 'svelte', '@sveltejs/kit', 'next', 'nuxt', '@inertiajs/react', '@inertiajs/vue3', 'nx']
            )),
        ];
    }

    /**
     * The agent rules that govern some files about to be written, or the
     * target projects when no file is named.
     *
     * @param  list<string>  $files
     * @param  list<string>|null  $projects
     * @return array<string, mixed>
     */
    public function rules(array $files = [], ?array $projects = null, ?string $path = null): array
    {
        $resolved = $this->resolveRoot($path);

        if (isset($resolved['error'])) {
            return $resolved['error'];
        }

        $repository = $resolved['root'];
        $location = WorkspaceLocator::locate($repository, $resolved['configured'] ? $this->config->frontendWorkspacePath() : null);
        $root = $location['root'];
        $index = RepoIndex::build($root);
        $rules = new AgentRules($index);

        if ($files !== []) {
            [$relative, $outside] = $this->relativeFiles($root, $files);

            if ($outside !== []) {
                return [
                    'ok' => false,
                    'error' => 'Files outside the frontend repository: '.implode(', ', $outside).'.',
                    'path' => $repository,
                ];
            }

            // Paths are relative to the workspace root; a path written from
            // the repository of a project cloned inside it is read there.
            if ($location['project_root'] !== null) {
                $relative = array_map(function (string $file) use ($root, $repository, $location): string {
                    $known = static fn (string $base): bool => file_exists($base.'/'.$file) || is_dir(dirname($base.'/'.$file));

                    return ! $known($root) && $known($repository) ? $location['project_root'].'/'.$file : $file;
                }, $relative);
            }

            $answer = $rules->forFiles($relative);
            $enclosing = $location['source'] === 'repository' ? WorkspaceLocator::enclosing($repository) : null;
            $inherited = $enclosing !== null ? AgentRules::inherited($enclosing, $repository, $relative) : null;

            return ['ok' => true, 'path' => $repository, 'root' => $root, 'files' => $relative]
                + ($inherited !== null ? ['inherited' => $inherited] : [])
                + $answer;
        }

        $workspace = (new WorkspaceInspector($root, $index))->inspect(false);
        $requested = $projects ?? ($resolved['configured'] ? $this->config->frontend()['projects'] : []);

        if ($requested === [] && $location['project_root'] !== null) {
            $requested = [$location['project_root']];
        }

        $targets = $this->targets($workspace, $requested);
        $scope = $this->writeScope($workspace, $targets['resolved']);
        $roots = array_values(array_unique(array_map(static fn (array $owned): string => (string) $owned['root'], $scope['owned'])));

        $enclosing = $location['source'] === 'repository' ? WorkspaceLocator::enclosing($repository) : null;
        $inherited = $enclosing !== null ? AgentRules::inherited($enclosing, $repository) : null;

        return ['ok' => true, 'path' => $repository, 'root' => $root, 'projects' => $targets['resolved']]
            + ($inherited !== null ? ['inherited' => $inherited] : [])
            + $rules->forProjects($roots);
    }

    /**
     * The repository a frontend task commits to: the linked one, or the
     * repository of its project when that project is cloned inside the
     * workspace as a repository of its own.
     */
    public function gitRootFor(?string $project = null): ?string
    {
        $repository = $this->config->frontendRepoPath();

        if (! is_string($repository) || ! is_dir($repository)) {
            return null;
        }

        if ($project !== null && trim($project) !== '') {
            $location = WorkspaceLocator::locate($repository, $this->config->frontendWorkspacePath());
            $known = $this->projectsOf($repository, $this->config->frontendWorkspacePath());
            $name = self::matchProject($project, $known['projects']);

            if ($name !== null) {
                $directory = $known['projects'][$name]['root'] === '.' ? $location['root'] : $location['root'].'/'.$known['projects'][$name]['root'];
                $top = RepoGit::toplevel($directory);

                if ($top !== null) {
                    return $top;
                }
            }
        }

        return RepoGit::toplevel($repository) ?? $repository;
    }

    /**
     * The projects of a workspace, by name — for `frontend-set --project`.
     *
     * @return array{kind: string, projects: array<string, array{root: string, type: string, package: string|null}>}
     */
    public function projectsOf(string $repository, ?string $workspacePath = null): array
    {
        $root = WorkspaceLocator::locate($repository, $workspacePath)['root'];
        $index = RepoIndex::build($root);
        $workspace = (new WorkspaceInspector($root, $index))->inspect(false);
        $projects = [];

        foreach ($workspace['projects'] as $name => $project) {
            $projects[$name] = [
                'root' => (string) $project['root'],
                'type' => (string) $project['type'],
                'package' => is_string($project['package'] ?? null) ? $project['package'] : null,
            ];
        }

        return ['kind' => $workspace['kind'], 'projects' => $projects];
    }

    /**
     * A name the user gave — project name, package name, or folder — as the
     * project name the workspace uses.
     *
     * @param  array<string, array<string, mixed>>  $projects
     */
    public static function matchProject(string $given, array $projects): ?string
    {
        $given = trim($given);

        if (isset($projects[$given])) {
            return $given;
        }

        $folder = trim(str_replace('\\', '/', $given), '/');

        foreach ($projects as $name => $project) {
            if (($project['package'] ?? null) === $given || ($project['root'] ?? null) === $folder) {
                return (string) $name;
            }
        }

        return null;
    }

    /**
     * @return array{root: string, configured: bool}|array{error: array<string, mixed>}
     */
    protected function resolveRoot(?string $path): array
    {
        $configured = $this->config->frontendRepoPath();
        $repoPath = $path ?? $configured;

        if (! is_string($repoPath) || $repoPath === '') {
            return ['error' => [
                'ok' => false,
                'error' => 'No frontend repo path configured. Run larapilot:frontend-set --path=/absolute/path.',
            ]];
        }

        $validation = $this->validatePath($repoPath);

        if (! $validation['valid']) {
            return ['error' => [
                'ok' => false,
                'path' => $validation['path'],
                'errors' => $validation['errors'],
            ]];
        }

        $root = realpath($validation['path']) ?: $validation['path'];
        $configuredRoot = is_string($configured) && $configured !== '' ? (realpath($configured) ?: $configured) : null;

        return ['root' => $root, 'configured' => $path === null || $configuredRoot === $root];
    }

    /**
     * Which projects the scan describes in full. A single app is its own
     * target; a monorepo needs the user to name the projects of this product.
     *
     * @param  array<string, mixed>  $workspace
     * @param  list<string>  $requested
     * @return array{resolved: list<string>, summary: array<string, mixed>}
     */
    protected function targets(array $workspace, array $requested): array
    {
        $projects = $workspace['projects'];
        $resolved = [];
        $missing = [];

        foreach ($requested as $given) {
            $name = self::matchProject((string) $given, $projects);

            if ($name === null) {
                $missing[] = (string) $given;
            } elseif (! in_array($name, $resolved, true)) {
                $resolved[] = $name;
            }
        }

        if ($resolved === [] && $missing === [] && count($projects) === 1) {
            $resolved = [(string) array_key_first($projects)];
        }

        $applications = array_keys(array_filter($projects, static fn (array $project): bool => $project['type'] === 'application'));
        $needsProject = $resolved === [] && count($projects) > 1;

        return [
            'resolved' => $resolved,
            'summary' => array_filter([
                'configured' => $requested,
                'resolved' => $resolved,
                'missing' => $missing === [] ? null : $missing,
                'needs_project' => $needsProject,
                'candidates' => $needsProject ? array_slice($applications === [] ? array_keys($projects) : $applications, 0, 40) : null,
                'suggested' => $needsProject ? $this->suggest($applications) : null,
            ], static fn (mixed $value): bool => $value !== null && $value !== []) + ['needs_project' => $needsProject],
        ];
    }

    /**
     * Applications whose name shares a word with this Laravel product.
     *
     * @param  list<string>  $applications
     * @return list<string>|null
     */
    protected function suggest(array $applications): ?array
    {
        $composer = RepoFiles::json($this->config->projectRoot().'/composer.json');
        $words = strtolower(implode(' ', array_filter([
            is_string(config('app.name')) ? config('app.name') : null,
            is_string($composer['name'] ?? null) ? $composer['name'] : null,
            basename($this->config->projectRoot()),
        ])));

        $tokens = array_filter(
            preg_split('/[^a-z0-9]+/', $words) ?: [],
            static fn (string $token): bool => strlen($token) >= 3 && ! in_array($token, ['laravel', 'app', 'api', 'backend', 'server', 'web', 'the'], true)
        );

        $suggested = array_values(array_filter($applications, static function (string $application) use ($tokens): bool {
            foreach ($tokens as $token) {
                if (str_contains(strtolower($application), $token)) {
                    return true;
                }
            }

            return false;
        }));

        return $suggested === [] ? null : array_slice($suggested, 0, 5);
    }

    /**
     * Where the agent writes freely, and which libraries other apps share.
     *
     * A library is owned when every application that depends on it is a
     * target, or when it carries a `scope:` tag of a target. A library some
     * other application also uses is shared: changing it changes that app.
     *
     * @param  array<string, mixed>  $workspace
     * @param  list<string>  $targets
     * @return array{owned: list<array{name: string, root: string}>, shared: list<array<string, mixed>>, vendored: list<array{path: string, package: string|null}>, rule: string}
     */
    protected function writeScope(array $workspace, array $targets): array
    {
        $projects = $workspace['projects'];
        $dependencies = $workspace['dependencies'];
        $rule = 'Write inside the owned roots. A shared library is used by other applications: change it only when the task names it under `shared` with the reason, keep its public API backward compatible, and run the `affected` command before committing. A vendored package is built third-party code: never edit it. Any other folder belongs to another team — ask first.';

        if ($targets === []) {
            return ['owned' => [], 'shared' => [], 'vendored' => [], 'rule' => $rule];
        }

        $reverse = [];

        foreach ($dependencies as $source => $list) {
            foreach ($list as $target) {
                $reverse[$target][] = $source;
            }
        }

        $closure = [];
        $queue = $targets;

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($dependencies[$current] ?? [] as $dependency) {
                if (! isset($closure[$dependency]) && ! in_array($dependency, $targets, true)) {
                    $closure[$dependency] = true;
                    $queue[] = $dependency;
                }
            }
        }

        $scopes = [];

        foreach ($targets as $target) {
            foreach ($projects[$target]['tags'] ?? [] as $tag) {
                if (is_string($tag) && str_starts_with($tag, 'scope:') && $tag !== 'scope:shared') {
                    $scopes[$tag] = true;
                }
            }
        }

        $owned = array_map(static fn (string $name): array => ['name' => $name, 'root' => (string) $projects[$name]['root']], $targets);
        $shared = [];

        foreach (array_keys($closure) as $library) {
            $apps = $this->dependentApplications($library, $reverse, $projects);
            $others = array_values(array_diff($apps, $targets));
            $tagged = array_intersect_key($scopes, array_flip($projects[$library]['tags'] ?? [])) !== [];

            if ($others === [] || $tagged) {
                $owned[] = ['name' => $library, 'root' => (string) $projects[$library]['root']];

                continue;
            }

            $shared[] = [
                'name' => $library,
                'root' => (string) $projects[$library]['root'],
                'used_by' => count($others),
                'apps' => array_slice($others, 0, 10),
            ];
        }

        usort($shared, static fn (array $a, array $b): int => $b['used_by'] <=> $a['used_by'] ?: strcmp($a['name'], $b['name']));

        return ['owned' => $owned, 'shared' => $shared, 'vendored' => [], 'rule' => $rule];
    }

    /**
     * @param  array<string, list<string>>  $reverse
     * @param  array<string, array<string, mixed>>  $projects
     * @return list<string>
     */
    protected function dependentApplications(string $library, array $reverse, array $projects): array
    {
        $seen = [];
        $queue = [$library];
        $apps = [];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($reverse[$current] ?? [] as $dependent) {
                if (isset($seen[$dependent])) {
                    continue;
                }

                $seen[$dependent] = true;
                $queue[] = $dependent;

                if (($projects[$dependent]['type'] ?? null) === 'application') {
                    $apps[] = $dependent;
                }
            }
        }

        sort($apps);

        return $apps;
    }

    /**
     * @param  array<string, mixed>  $workspace
     * @return list<array<string, mixed>>
     */
    protected function projectList(array $workspace, bool $full): array
    {
        $list = [];

        foreach ($workspace['projects'] as $name => $project) {
            $entry = [
                'name' => $name,
                'root' => $project['root'],
                'type' => $project['type'],
                'stack' => $project['stack'],
            ];

            if ($project['tags'] !== []) {
                $entry['tags'] = $project['tags'];
            }

            if ($full) {
                $entry['targets'] = array_keys($project['targets']);
                $entry['depends_on'] = $workspace['dependencies'][$name] ?? [];
            }

            $list[] = $entry;
        }

        if ($full || count($list) <= self::COMPACT_PROJECTS) {
            return $list;
        }

        // Applications first: they are what a user picks as a target.
        usort($list, static fn (array $a, array $b): int => ($a['type'] === 'application' ? 0 : 1) <=> ($b['type'] === 'application' ? 0 : 1) ?: strcmp($a['name'], $b['name']));

        return array_slice($list, 0, self::COMPACT_PROJECTS);
    }

    /**
     * Built packages copied into the project (`bundles/`, `fesm2022/`, …):
     * third-party code, replaced whole, never edited.
     *
     * @param  list<array{name: string, root: string}>  $owned
     * @return list<array{path: string, package: string|null}>
     */
    protected function vendored(RepoIndex $index, array $owned): array
    {
        $vendored = [];
        $roots = array_map(static fn (array $project): string => $project['root'], $owned);

        foreach ($index->named('package.json') as $file) {
            $directory = RepoFiles::dirname($file);

            if (in_array($directory, $roots, true) || $directory === '.') {
                continue;
            }

            $underOwned = false;

            foreach ($roots as $root) {
                $underOwned = $underOwned || RepoFiles::isUnder($directory, $root);
            }

            if (! $underOwned) {
                continue;
            }

            $built = false;

            foreach (scandir($index->absolute($directory)) ?: [] as $entry) {
                $built = $built || preg_match('/^(bundles|fesm\d{4}|esm\d{4}|esm5|fesm5|umd)$/', $entry) === 1;
            }

            if ($built) {
                $package = RepoFiles::json($index->absolute($file));
                $vendored[] = ['path' => $directory, 'package' => is_string($package['name'] ?? null) ? $package['name'] : null];
            }
        }

        return array_slice($vendored, 0, 20);
    }

    /**
     * @param  list<string>  $stacks
     * @return list<string>
     */
    protected function playbooks(array $stacks): array
    {
        $playbooks = [];

        foreach ($stacks as $stack) {
            $family = WorkspaceInspector::PLAYBOOKS[$stack] ?? null;

            if ($family !== null) {
                $playbooks['.larapilot/runtime-frontend-'.$family.'.md'] = true;
            }
        }

        return array_keys($playbooks);
    }

    /**
     * @param  array<string, mixed>  $workspace
     * @param  array{resolved: list<string>, summary: array<string, mixed>}  $targets
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $location
     * @param  list<array<string, mixed>>  $targetProjects
     * @return list<string>
     */
    protected function warnings(array $workspace, array $targets, array $rules, string $mode, array $location = [], array $targetProjects = []): array
    {
        $warnings = [];
        $exec = (string) $workspace['package_manager']['exec'];
        $name = $targetProjects[0]['name'] ?? 'the project';

        if (($location['source'] ?? null) === 'missing') {
            $warnings[] = sprintf(
                'This repository is the project %s of a workspace that is not here (%s)%s. Its commands, its shared libraries, and maybe its agent rules live in that workspace: clone this repository at its place inside it, or link it with php artisan larapilot:frontend-set --workspace=/absolute/path. Until then nothing can be built or tested here (run_in is null).',
                $name,
                implode('; ', $location['signals'] ?? []),
                ($location['expected_root'] ?? null) !== null ? ', and expects to sit at '.$location['expected_root'] : ''
            );
        } elseif (in_array($location['source'] ?? null, ['ancestor', 'configured'], true) && ($location['project_root'] ?? null) === null && $targets['resolved'] === []) {
            $warnings[] = 'The linked workspace does not hold this repository: name its project with frontend-set --project=<name>.';
        }

        foreach ($targetProjects as $project) {
            foreach ($project['unavailable'] ?? [] as $target => $reason) {
                $warnings[] = sprintf('Target %s of %s cannot run: %s. Never run it; say so in the handoff.', $target, $project['name'], $reason);
            }

            $tests = $project['tests'] ?? [];

            if (($tests['spec_files'] ?? 0) > 0 && ($tests['runnable'] ?? false) === false) {
                $warnings[] = sprintf('%s has %d spec files and no test target that runs: its tests cannot be run here. Never invent a command; say so in the handoff.', $project['name'], $tests['spec_files']);
            }

            if (($tests['spec_files'] ?? 0) === 0 && ($tests['runnable'] ?? false) === true) {
                $warnings[] = sprintf(
                    '%s has a test target and no spec file yet%s: its test command finds nothing to run. Whether this task writes the first ones is the user\'s call (settings.testing against the team\'s habit): ask once, record it with decision-log.',
                    $project['name'],
                    ($tests['generators_skip'] ?? false) ? ' — its generators skip them (skipTests=true)' : ''
                );
            }
        }

        if ($targets['summary']['needs_project'] === true) {
            $warnings[] = sprintf(
                'This %s workspace holds %d projects and no target is set. Ask the user which of them belong to this product, then run php artisan larapilot:frontend-set --project=<name> (repeat --project for each).',
                $workspace['kind'],
                count($workspace['projects'])
            );
        }

        if (($targets['summary']['missing'] ?? []) !== []) {
            $warnings[] = 'Configured projects not found in the workspace: '.implode(', ', $targets['summary']['missing']).'. Check the names with --full and run frontend-set --project again.';
        }

        if ($workspace['kind'] === 'nx' && $workspace['graph']['source'] !== 'nx-cli') {
            $warnings[] = 'The Nx graph was read from the files, not from nx: '.rtrim((string) ($workspace['graph']['error'] ?? 'nx was not run'), '.').'. Dependencies come from imports and package.json'
                .($workspace['graph']['targets_complete'] ? '.' : sprintf('; targets added by plugins this scan does not know may be missing — run %s nx show project <name> --json before trusting a missing target.', $exec));
        }

        if (! $workspace['package_manager']['installed'] && $workspace['has_package_json'] && ($location['source'] ?? null) !== 'missing') {
            $warnings[] = sprintf('Packages are not installed (no node_modules): run %s in the frontend root before any command.', $workspace['package_manager']['install']);
        }

        if ($rules['must_read'] === [] && $rules['conditional'] === [] && $rules['on_request'] === [] && ! isset($rules['inherited'])) {
            $warnings[] = 'The repository gives agents no rules (no AGENTS.md, CLAUDE.md, .cursor/rules, copilot-instructions, …): follow the observed conventions and the exemplars, and say so in the handoff.';
        }

        if (($workspace['truncated'] ?? false) === true) {
            $warnings[] = 'The repository is larger than the scan walks: projects or rules deep in the tree may be missing.';
        }

        if ($mode === 'handoff') {
            $warnings[] = 'Mode is handoff: the frontend team builds the UI from php artisan larapilot:frontend-brief {code}; this workspace does not write frontend code.';
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $project
     * @return list<string>
     */
    protected function entrypoints(string $root, array $project): array
    {
        $base = $project['root'] === '.' ? '' : $project['root'].'/';
        $found = [];

        foreach ([
            'src/main.ts', 'src/main.tsx', 'src/main.js', 'src/main.jsx', 'src/index.tsx', 'src/index.ts',
            'src/App.tsx', 'src/App.vue', 'src/app/app.config.ts', 'src/app/app.routes.ts', 'src/app/app.module.ts',
            'app/page.tsx', 'app/page.jsx', 'src/app/page.tsx', 'app/app.vue', 'app.vue', 'pages/index.tsx', 'pages/index.vue',
            'src/routes/+page.svelte', 'src/routes/+layout.svelte',
        ] as $relative) {
            if (is_file($root.'/'.$base.$relative)) {
                $found[] = $base.$relative;
            }
        }

        if ($found === []) {
            $package = RepoFiles::json($root.'/'.$base.'package.json');
            $main = $package['main'] ?? null;

            if (is_string($main) && is_file($root.'/'.$base.$main)) {
                $found[] = $base.$main;
            }
        }

        return $found;
    }

    /**
     * An absolute path through its real folders (`/var` is `/private/var`
     * on macOS), for a file that may not exist yet.
     */
    protected function realFile(string $path): string
    {
        $missing = [];
        $directory = $path;

        while ($directory !== '/' && $directory !== '' && ! file_exists($directory)) {
            array_unshift($missing, basename($directory));
            $directory = dirname($directory);
        }

        $real = realpath($directory);

        if ($real === false) {
            return $path;
        }

        return rtrim($real, '/').($missing === [] ? '' : '/'.implode('/', $missing));
    }

    /**
     * @param  list<string>  $files
     * @return array{0: list<string>, 1: list<string>}
     */
    protected function relativeFiles(string $root, array $files): array
    {
        $relative = [];
        $outside = [];

        foreach ($files as $file) {
            foreach (explode(',', $file) as $part) {
                $part = str_replace('\\', '/', trim($part));

                if ($part === '') {
                    continue;
                }

                if (str_starts_with($part, '/')) {
                    $real = $this->realFile($part);

                    if (! str_starts_with($real, $root.'/')) {
                        $outside[] = $part;

                        continue;
                    }

                    $part = substr($real, strlen($root) + 1);
                }

                $segments = [];

                foreach (explode('/', $part) as $segment) {
                    if ($segment === '' || $segment === '.') {
                        continue;
                    }

                    if ($segment === '..') {
                        if ($segments === []) {
                            $outside[] = $part;

                            continue 2;
                        }

                        array_pop($segments);

                        continue;
                    }

                    $segments[] = $segment;
                }

                if ($segments !== []) {
                    $relative[] = implode('/', $segments);
                }
            }
        }

        return [array_values(array_unique($relative)), $outside];
    }
}
