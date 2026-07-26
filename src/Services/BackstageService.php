<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Illuminate\Support\Str;
use Larapilot\LarapilotServiceProvider;
use Larapilot\Support\ArtifactSections;
use Larapilot\Support\AtomicFile;
use Larapilot\Support\Markdown;
use Larapilot\Support\SpecCode;
use Symfony\Component\Yaml\Yaml;

/**
 * Projects the `.larapilot/` workspace onto Backstage (backstage.io):
 * a software-catalog descriptor, a TechDocs site, and a live delivery
 * snapshot a Backstage plugin or entity provider can poll.
 *
 * Nothing is written to the project until `write()` is called.
 */
class BackstageService
{
    public function __construct(
        protected ConfigService $config,
        protected DashboardService $dashboard,
        protected SpecService $specs,
        protected PlanService $plans,
        protected PrdService $prd,
        protected InternalFeedbackService $feedback,
        protected CompanionService $companion,
        protected OpenApiService $openApi,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('larapilot.enabled', true)
            && (bool) config('larapilot.backstage.enabled', true);
    }

    /**
     * Full integration payload: catalog entities, TechDocs metadata, and the
     * delivery snapshot. Served by the API and by `backstage-export`.
     *
     * @return array<string, mixed>
     */
    public function bundle(?string $apiBaseUrl = null): array
    {
        $entities = $this->entities($apiBaseUrl);

        return [
            'generated_at' => now()->toIso8601String(),
            'source' => 'larapilot',
            'version' => LarapilotServiceProvider::VERSION,
            'skill' => 'larapilot-backstage',
            'catalog' => [
                'path' => $this->config->relativePath($this->catalogPath()),
                'entity_refs' => array_map(fn (array $entity): string => $this->refFor($entity), $entities),
                'entities' => $entities,
                'yaml' => $this->catalogYaml($apiBaseUrl),
            ],
            'techdocs' => [
                'enabled' => $this->techdocsEnabled(),
                'docs_dir' => $this->techdocsDir(),
                'mkdocs_path' => $this->config->relativePath($this->mkdocsPath()),
                'pages' => $this->techdocsEnabled() ? $this->techdocsPageNames() : [],
            ],
            'snapshot' => $this->snapshot($apiBaseUrl),
            'instructions' => $this->instructions($apiBaseUrl),
        ];
    }

    /**
     * Live delivery state for a Backstage entity card or entity provider —
     * intentionally lean (no spec bodies, no plan text) so a portal can poll
     * many repositories cheaply.
     *
     * @return array<string, mixed>
     */
    public function snapshot(?string $apiBaseUrl = null): array
    {
        $board = $this->dashboard->rawBoard();
        $stories = [];
        $blockingSpecs = [];
        $blockingTotal = 0;

        foreach ($this->specs->allSpecs() as $spec) {
            $code = (string) ($spec['code'] ?? '');

            if ($code === '') {
                continue;
            }

            $blocking = $this->feedback->blockingCount($code);

            if ($blocking > 0) {
                $blockingSpecs[] = $code;
                $blockingTotal += $blocking;
            }

            $stories[] = [
                'code' => $code,
                'title' => (string) ($spec['title'] ?? ''),
                'status' => (string) ($spec['status'] ?? 'TODO'),
                'priority' => (string) ($spec['priority'] ?? ''),
                'points' => (int) ($spec['points'] ?? 0),
                'epic' => $this->epicOf($spec),
                'tasks' => $this->plans->taskProgress($code),
                'blocking_feedback' => $blocking,
                'techdocs_path' => $this->techdocsEnabled() && SpecCode::isValid($code)
                    ? 'backlog/'.$code.'.md'
                    : null,
            ];
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'entity_ref' => $this->entityRef(),
            'component' => [
                'name' => $this->componentName(),
                'namespace' => $this->entityNamespace(),
                'title' => $this->title(),
                'lifecycle' => $this->lifecycle(),
                'owner' => $this->owner(),
                'system' => $this->system(),
            ],
            'metrics' => $board['metrics'],
            'status_order' => $board['statusOrder'],
            'counts_by_status' => array_map('count', $board['columns']),
            'blocking_feedback' => [
                'count' => $blockingTotal,
                'specs' => $blockingSpecs,
            ],
            'prd' => [
                'available' => $this->prd->exists(),
                'path' => $this->config->relativePath($this->prd->path()),
            ],
            'stories' => $stories,
            'links' => $this->resolveUrls($apiBaseUrl),
        ];
    }

    /* ---------------------------------------------------------------------
     | Catalog entities
     |--------------------------------------------------------------------- */

    /**
     * Backstage catalog entities for this repository: one Component, plus an
     * API entity per OpenAPI contract worth registering.
     *
     * @return list<array<string, mixed>>
     */
    public function entities(?string $apiBaseUrl = null, ?string $catalogPath = null): array
    {
        $catalogDirectory = dirname($catalogPath ?? $this->catalogPath());
        $name = $this->componentName();
        $apis = [];
        $providesApis = [];

        $productOpenApi = $this->companion->productOpenApiPath();

        if ($productOpenApi !== null) {
            $apiName = $this->normalizeName($name.'-api');
            $providesApis[] = $apiName;
            $apis[] = $this->apiEntity(
                $apiName,
                $this->title().' API',
                'Product API contract published by '.$this->title().'.',
                $this->relativeReference($catalogDirectory, $productOpenApi),
            );
        }

        if ($this->workflowApiEnabled()) {
            $apiName = $this->normalizeName($name.'-larapilot-workflow');
            $providesApis[] = $apiName;
            $apis[] = $this->apiEntity(
                $apiName,
                $this->title().' — Larapilot workflow API',
                'Read-only Larapilot delivery API (backlog, plans, PRD, diagnostics). Available outside production only.',
                $this->relativeReference($catalogDirectory, $this->workflowOpenApiPath()),
            );
        }

        return array_values(array_merge(
            [$this->componentEntity($providesApis, $apiBaseUrl)],
            $apis
        ));
    }

    public function catalogYaml(?string $apiBaseUrl = null, ?string $catalogPath = null): string
    {
        $documents = array_map(
            fn (array $entity): string => rtrim(Yaml::dump($entity, 8, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)),
            $this->entities($apiBaseUrl, $catalogPath)
        );

        return "# Generated by Larapilot (php artisan larapilot:backstage-export --write).\n"
            ."# Edit config/larapilot.php → backstage, then regenerate.\n"
            .'---'."\n"
            .implode("\n---\n", $documents)
            ."\n";
    }

    /**
     * @param  list<string>  $providesApis
     * @return array<string, mixed>
     */
    protected function componentEntity(array $providesApis, ?string $apiBaseUrl): array
    {
        $urls = $this->resolveUrls($apiBaseUrl);

        $annotations = [
            'larapilot.io/version' => LarapilotServiceProvider::VERSION,
            'larapilot.io/workspace' => '.larapilot',
        ];

        if ($this->techdocsEnabled()) {
            $annotations = ['backstage.io/techdocs-ref' => 'dir:.'] + $annotations;
        }

        if ($this->prd->exists()) {
            $annotations['larapilot.io/prd'] = $this->config->relativePath($this->prd->path());
        }

        if ($urls['board'] !== null) {
            $annotations['larapilot.io/board-url'] = $urls['board'];
            $annotations['larapilot.io/api-url'] = (string) $urls['api'];
        }

        $metadata = $this->prune([
            'name' => $this->componentName(),
            'namespace' => $this->entityNamespace(),
            'title' => $this->title(),
            'description' => $this->description(),
            'annotations' => $annotations,
            'tags' => $this->tags(),
            'links' => $this->links($urls),
        ]);

        $spec = $this->prune([
            'type' => $this->componentType(),
            'lifecycle' => $this->lifecycle(),
            'owner' => $this->owner(),
            'system' => $this->system(),
            'providesApis' => $providesApis,
        ]);

        return [
            'apiVersion' => 'backstage.io/v1alpha1',
            'kind' => 'Component',
            'metadata' => $metadata,
            'spec' => $spec,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function apiEntity(string $name, string $title, string $description, string $definitionRef): array
    {
        return [
            'apiVersion' => 'backstage.io/v1alpha1',
            'kind' => 'API',
            'metadata' => $this->prune([
                'name' => $name,
                'namespace' => $this->entityNamespace(),
                'title' => $title,
                'description' => $description,
                'tags' => $this->tags(),
            ]),
            'spec' => $this->prune([
                'type' => 'openapi',
                'lifecycle' => $this->lifecycle(),
                'owner' => $this->owner(),
                'system' => $this->system(),
                // Backstage resolves $text placeholders relative to the
                // catalog-info.yaml location when ingesting the entity.
                'definition' => ['$text' => $definitionRef],
            ]),
        ];
    }

    /**
     * @param  array{board: string|null, api: string|null}  $urls
     * @return list<array<string, string>>
     */
    protected function links(array $urls): array
    {
        if ($urls['board'] === null) {
            return [];
        }

        return [
            ['url' => $urls['board'], 'title' => 'Larapilot board', 'icon' => 'dashboard'],
            ['url' => $urls['board'].'/api/docs', 'title' => 'Larapilot API docs', 'icon' => 'docs'],
        ];
    }

    /* ---------------------------------------------------------------------
     | TechDocs
     |--------------------------------------------------------------------- */

    public function techdocsEnabled(): bool
    {
        return (bool) config('larapilot.backstage.techdocs.enabled', true);
    }

    public function techdocsDir(): string
    {
        $dir = trim((string) config('larapilot.backstage.techdocs.docs_dir', '.larapilot/techdocs'));

        return rtrim($dir === '' ? '.larapilot/techdocs' : $dir, '/');
    }

    /**
     * MkDocs configuration Backstage TechDocs builds the site from.
     *
     * @return array<string, mixed>
     */
    public function mkdocs(): array
    {
        return $this->prune([
            'site_name' => $this->title(),
            'site_description' => $this->description(),
            'docs_dir' => $this->techdocsDir(),
            'plugins' => ['techdocs-core'],
            'nav' => $this->nav(),
        ]);
    }

    public function mkdocsYaml(): string
    {
        return "# Generated by Larapilot (php artisan larapilot:backstage-export --write).\n"
            .'# Sources live in '.$this->techdocsDir()."/ and are regenerated from .larapilot/.\n"
            .Yaml::dump($this->mkdocs(), 8, 2);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function nav(): array
    {
        $stories = [];

        foreach ($this->publishableSpecs() as $code => $spec) {
            $title = (string) ($spec['title'] ?? '');
            $label = $title === '' ? $code : $code.' — '.$title;

            $stories[] = [$label => 'backlog/'.$code.'.md'];
        }

        $nav = [
            ['Overview' => 'index.md'],
            ['Product Requirements' => 'prd.md'],
        ];

        $nav[] = ['Backlog' => array_merge([['Overview' => 'backlog/index.md']], $stories)];

        return $nav;
    }

    /**
     * Page paths without rendering their Markdown — the polling endpoints
     * only need the index, not the content.
     *
     * @return list<string>
     */
    public function techdocsPageNames(): array
    {
        $names = ['index.md', 'prd.md', 'backlog/index.md'];

        foreach (array_keys($this->publishableSpecs()) as $code) {
            $names[] = 'backlog/'.$code.'.md';
        }

        return $names;
    }

    /**
     * Markdown sources for the TechDocs site, keyed by path relative to the
     * docs directory. Everything here is generated from `.larapilot/`.
     *
     * @return array<string, string>
     */
    public function techdocsPages(): array
    {
        $pages = [
            'index.md' => $this->overviewPage(),
            'prd.md' => $this->prdPage(),
            'backlog/index.md' => $this->backlogIndexPage(),
        ];

        foreach ($this->publishableSpecs() as $code => $spec) {
            $pages['backlog/'.$code.'.md'] = $this->storyPage($spec);
        }

        return $pages;
    }

    protected function overviewPage(): string
    {
        $board = $this->dashboard->rawBoard();
        $metrics = $board['metrics'];
        $description = $this->description();

        $lines = ['# '.$this->title(), ''];

        if ($description !== null) {
            $lines[] = $description;
            $lines[] = '';
        }

        $lines[] = $this->generatedNotice();
        $lines[] = '';
        $lines[] = '## Delivery snapshot';
        $lines[] = '';
        $lines[] = '| Metric | Value |';
        $lines[] = '| --- | --- |';
        $lines[] = '| User stories | '.(int) ($metrics['total'] ?? 0).' |';
        $lines[] = '| Done | '.(int) ($metrics['done'] ?? 0).' ('.$this->number($metrics['completion_rate'] ?? 0).'%) |';
        $lines[] = '| Work in progress | '.(int) ($metrics['wip'] ?? 0).' |';
        $lines[] = '| Story points | '.(int) ($metrics['done_points'] ?? 0).' of '.(int) ($metrics['total_points'] ?? 0)
            .' ('.$this->number($metrics['points_completion_rate'] ?? 0).'%) |';
        $lines[] = '| Plan tasks | '.(int) ($metrics['done_tasks'] ?? 0).' of '.(int) ($metrics['total_tasks'] ?? 0).' |';
        $lines[] = '';
        $lines[] = '## Stories by status';
        $lines[] = '';
        $lines[] = '| Status | Stories |';
        $lines[] = '| --- | --- |';

        foreach ($board['columns'] as $status => $specs) {
            $lines[] = '| '.$this->escapeCell((string) $status).' | '.count($specs).' |';
        }

        $lines[] = '';
        $lines[] = '## Documents';
        $lines[] = '';
        $lines[] = '- [Product Requirements](prd.md)';
        $lines[] = '- [Backlog](backlog/index.md)';

        return $this->page($lines);
    }

    protected function prdPage(): string
    {
        $content = $this->prd->read();

        if ($content === null || trim($content) === '') {
            return $this->page([
                '# Product Requirements',
                '',
                'No PRD yet. Run `/larapilot-inception` to write `'.$this->config->relativePath($this->prd->path()).'`.',
                '',
                $this->generatedNotice(),
            ]);
        }

        $body = trim($content);

        if (preg_match('/^#\s+\S/', $body) !== 1) {
            $body = "# Product Requirements\n\n".$body;
        }

        return $this->page([$body, '', '---', '', $this->generatedNotice()]);
    }

    protected function backlogIndexPage(): string
    {
        $specs = $this->publishableSpecs();
        $lines = ['# Backlog', ''];
        $lines[] = count($specs).' user '.(count($specs) === 1 ? 'story' : 'stories')
            .' from `'.$this->config->relativePath($this->specs->backlogPath()).'`.';
        $lines[] = '';
        $lines[] = $this->generatedNotice();
        $lines[] = '';

        if ($specs === []) {
            $lines[] = 'The backlog is empty. Run `/larapilot-spec` to build it from the PRD.';

            return $this->page($lines);
        }

        $grouped = [];

        foreach ($specs as $spec) {
            $epic = $this->epicOf($spec);
            $key = $epic === null ? '' : $epic['code'].' — '.$epic['title'];
            $grouped[$key][] = $spec;
        }

        foreach ($grouped as $epicLabel => $items) {
            $lines[] = '## '.($epicLabel === '' ? 'Unassigned' : $epicLabel);
            $lines[] = '';
            $lines[] = '| Story | Status | Priority | Points | Tasks |';
            $lines[] = '| --- | --- | --- | --- | --- |';

            foreach ($items as $spec) {
                $code = (string) ($spec['code'] ?? '');
                $progress = $this->plans->taskProgress($code);

                $lines[] = '| ['.$code.']('.$code.'.md) — '.$this->escapeCell((string) ($spec['title'] ?? ''))
                    .' | '.$this->escapeCell((string) ($spec['status'] ?? ''))
                    .' | '.$this->escapeCell((string) ($spec['priority'] ?? '—'))
                    .' | '.(int) ($spec['points'] ?? 0)
                    .' | '.(int) $progress['done'].'/'.(int) $progress['total'].' |';
            }

            $lines[] = '';
        }

        return $this->page($lines);
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    protected function storyPage(array $spec): string
    {
        $code = (string) ($spec['code'] ?? '');
        $title = (string) ($spec['title'] ?? '');
        $epic = $this->epicOf($spec);
        $progress = $this->plans->taskProgress($code);
        $feedback = $this->feedback->summary($code, $spec);
        $plan = $this->plans->read($code);
        $detail = $this->specs->show($code);

        $lines = ['# '.$code.($title === '' ? '' : ' — '.$title), ''];
        $lines[] = '| Field | Value |';
        $lines[] = '| --- | --- |';
        $lines[] = '| Status | '.$this->escapeCell((string) ($spec['status'] ?? '')).' |';
        $lines[] = '| Priority | '.$this->escapeCell((string) ($spec['priority'] ?? '—')).' |';
        $lines[] = '| Points | '.(int) ($spec['points'] ?? 0).' |';
        $lines[] = '| Epic | '.($epic === null ? '—' : $this->escapeCell($epic['code'].' — '.$epic['title'])).' |';
        $lines[] = '| Tasks | '.(int) $progress['done'].' of '.(int) $progress['total'].' done |';
        $lines[] = '| Internal feedback | '.(int) $feedback['entry_count'].' comments ('
            .(int) $feedback['blocking_count'].' blocking) |';
        $lines[] = '';
        $lines[] = $this->generatedNotice();
        $lines[] = '';

        $body = trim((string) ($spec['body'] ?? ''));

        if ($body !== '') {
            $lines[] = Markdown::demoteTopLevel($body);
            $lines[] = '';
        }

        $planBody = trim((string) ($plan['plan_body'] ?? ''));

        if ($planBody !== '') {
            $lines[] = '## Technical plan';
            $lines[] = '';
            $lines[] = Markdown::demoteTopLevel($planBody);
            $lines[] = '';
        }

        $tasks = $detail === null ? [] : $detail['tasks'];

        if ($tasks !== []) {
            $lines[] = '## Tasks';
            $lines[] = '';

            foreach ($tasks as $task) {
                if (! is_array($task)) {
                    continue;
                }

                $done = strtoupper((string) ($task['status'] ?? '')) === 'DONE';
                $type = trim((string) ($task['type'] ?? ''));

                $lines[] = '- ['.($done ? 'x' : ' ').'] **'.(string) ($task['id'] ?? '').'** — '
                    .(string) ($task['title'] ?? '')
                    .($type === '' ? '' : ' _('.$type.')_');
            }

            $lines[] = '';
        }

        return $this->page($lines);
    }

    /* ---------------------------------------------------------------------
     | Writing
     |--------------------------------------------------------------------- */

    /**
     * Write the catalog descriptor, MkDocs config, and TechDocs sources into
     * the project. Files the user may own (catalog-info.yaml, mkdocs.yml) are
     * never overwritten without `force`; generated pages under the docs dir
     * always are.
     *
     * @param  array{force?: bool, techdocs?: bool, api_base?: string|null, catalog?: string|null, mkdocs?: string|null}  $options
     * @return array<string, mixed>
     */
    public function write(array $options = []): array
    {
        $force = (bool) ($options['force'] ?? false);
        $withTechdocs = ($options['techdocs'] ?? true) && $this->techdocsEnabled();
        $apiBase = $options['api_base'] ?? null;

        $catalogPath = $this->absoluteOption($options['catalog'] ?? null, $this->catalogPath());
        $mkdocsPath = $this->absoluteOption($options['mkdocs'] ?? null, $this->mkdocsPath());

        $report = [
            'catalog' => $this->writeGuarded(
                $catalogPath,
                $this->catalogYaml($apiBase, $catalogPath),
                $force
            ),
            'entity_refs' => array_map(
                fn (array $entity): string => $this->refFor($entity),
                $this->entities($apiBase, $catalogPath)
            ),
            'techdocs' => ['enabled' => $withTechdocs, 'docs_dir' => $this->techdocsDir(), 'pages' => []],
        ];

        if ($this->workflowApiEnabled()) {
            $document = $this->openApi->document($this->resolveUrls($apiBase)['api'] ?? '/larapilot/api');
            $json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($json !== false) {
                AtomicFile::write($this->workflowOpenApiPath(), $json."\n");
                $report['workflow_openapi'] = $this->config->relativePath($this->workflowOpenApiPath());
            }
        }

        if (! $withTechdocs) {
            return $report;
        }

        $report['mkdocs'] = $this->writeGuarded($mkdocsPath, $this->mkdocsYaml(), $force);

        $pages = $this->techdocsPages();
        $docsRoot = $this->config->absolutePath($this->techdocsDir());

        foreach ($pages as $relative => $contents) {
            AtomicFile::write($docsRoot.'/'.$relative, $contents);
        }

        $this->pruneStalePages($docsRoot, array_keys($pages));

        $report['techdocs']['pages'] = array_keys($pages);

        return $report;
    }

    /**
     * @return array{path: string, written: bool, skipped: bool, reason: string|null}
     */
    protected function writeGuarded(string $path, string $contents, bool $force): array
    {
        $relative = $this->config->relativePath($path);

        if (is_file($path) && ! $force) {
            return [
                'path' => $relative,
                'written' => false,
                'skipped' => true,
                'reason' => 'exists',
            ];
        }

        AtomicFile::write($path, $contents);

        return [
            'path' => $relative,
            'written' => true,
            'skipped' => false,
            'reason' => null,
        ];
    }

    /**
     * Drop generated pages for specs that no longer exist, so a deleted story
     * cannot linger in the published TechDocs site.
     *
     * @param  list<string>  $keep
     */
    protected function pruneStalePages(string $docsRoot, array $keep): void
    {
        $backlogDirectory = $docsRoot.'/backlog';

        if (! is_dir($backlogDirectory)) {
            return;
        }

        $current = array_map(fn (string $page): string => basename($page), $keep);

        foreach (glob($backlogDirectory.'/*.md') ?: [] as $file) {
            if (! in_array(basename($file), $current, true)) {
                @unlink($file);
            }
        }
    }

    /* ---------------------------------------------------------------------
     | Paths and identity
     |--------------------------------------------------------------------- */

    public function catalogPath(): string
    {
        $file = trim((string) config('larapilot.backstage.catalog_file', 'catalog-info.yaml'));

        return $this->config->absolutePath($file === '' ? 'catalog-info.yaml' : $file);
    }

    public function mkdocsPath(): string
    {
        $file = trim((string) config('larapilot.backstage.mkdocs_file', 'mkdocs.yml'));

        return $this->config->absolutePath($file === '' ? 'mkdocs.yml' : $file);
    }

    public function workflowOpenApiPath(): string
    {
        return $this->config->absolutePath('.larapilot/backstage/larapilot-openapi.json');
    }

    public function workflowApiEnabled(): bool
    {
        return (bool) config('larapilot.backstage.workflow_api', false);
    }

    public function entityRef(): string
    {
        return 'component:'.$this->entityNamespace().'/'.$this->componentName();
    }

    public function componentName(): string
    {
        $configured = trim((string) config('larapilot.backstage.name', ''));
        $source = $configured !== '' ? $configured : (string) config('app.name', 'laravel');

        return $this->normalizeName($source);
    }

    public function entityNamespace(): string
    {
        $namespace = trim((string) config('larapilot.backstage.namespace', 'default'));

        return $namespace === '' ? 'default' : $this->normalizeName($namespace);
    }

    public function title(): string
    {
        $configured = trim((string) config('larapilot.backstage.title', ''));

        if ($configured !== '') {
            return $configured;
        }

        $appName = trim((string) config('app.name', ''));

        return $appName === '' ? Str::headline($this->componentName()) : $appName;
    }

    public function description(): ?string
    {
        $configured = trim((string) config('larapilot.backstage.description', ''));

        if ($configured !== '') {
            return $configured;
        }

        return $this->prdSummary()
            ?? $this->composerDescription()
            ?? 'Laravel application delivered with Larapilot spec-driven workflow.';
    }

    public function owner(): string
    {
        $owner = trim((string) config('larapilot.backstage.owner', 'guests'));

        return $owner === '' ? 'guests' : $owner;
    }

    public function system(): ?string
    {
        $system = trim((string) config('larapilot.backstage.system', ''));

        return $system === '' ? null : $system;
    }

    public function lifecycle(): string
    {
        $lifecycle = trim((string) config('larapilot.backstage.lifecycle', 'experimental'));

        return $lifecycle === '' ? 'experimental' : $lifecycle;
    }

    public function componentType(): string
    {
        $type = trim((string) config('larapilot.backstage.component_type', 'service'));

        return $type === '' ? 'service' : $type;
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        $tags = config('larapilot.backstage.tags', []);

        if (! is_array($tags)) {
            return [];
        }

        $normalized = [];

        foreach ($tags as $tag) {
            $slug = Str::slug((string) $tag);

            if ($slug !== '' && ! in_array($slug, $normalized, true)) {
                $normalized[] = $slug;
            }
        }

        return $normalized;
    }

    /**
     * Board and API URLs used in entity links/annotations. The HTTP layer
     * passes the request host; CLI runs fall back to configuration.
     *
     * @return array{board: string|null, api: string|null}
     */
    public function resolveUrls(?string $apiBaseUrl = null): array
    {
        if (is_string($apiBaseUrl) && trim($apiBaseUrl) !== '') {
            $api = rtrim(trim($apiBaseUrl), '/');
            $board = preg_replace('#/api$#', '', $api) ?? $api;

            return ['board' => $board, 'api' => $api];
        }

        $base = trim((string) (config('larapilot.backstage.base_url') ?: config('app.url', '')));
        $base = rtrim($base, '/');

        if ($base === '' || $base === 'http://localhost') {
            return ['board' => null, 'api' => null];
        }

        $board = $base.'/'.trim((string) config('larapilot.dashboard_route.prefix', 'larapilot'), '/');

        return ['board' => $board, 'api' => $board.'/api'];
    }

    /* ---------------------------------------------------------------------
     | Helpers
     |--------------------------------------------------------------------- */

    /**
     * @return list<string>
     */
    protected function instructions(?string $apiBaseUrl): array
    {
        $urls = $this->resolveUrls($apiBaseUrl);
        $catalog = $this->config->relativePath($this->catalogPath());

        $instructions = [
            'Run `php artisan larapilot:backstage-export --write` to generate `'.$catalog.'`'
                .($this->techdocsEnabled() ? ', `'.$this->config->relativePath($this->mkdocsPath()).'`, and `'.$this->techdocsDir().'/`' : '').'.',
            'Commit `'.$catalog.'`, then register it in Backstage (Create → Register existing component) or let catalog discovery find it.',
            'Set `backstage.owner` (and `backstage.system`) before registering — Backstage warns on entities whose owner does not resolve to a Group or User.',
            'Regenerate after PRD, backlog, or plan changes; a CI step keeps the catalog and TechDocs in sync with `.larapilot/`.',
        ];

        if ($urls['api'] !== null) {
            $instructions[] = 'Live delivery data for a Backstage plugin: `GET '.$urls['api'].'/backstage` (bundle) and `GET '.$urls['api'].'/backstage/catalog-info.yaml`.';
        }

        $instructions[] = 'Call the Larapilot API through the Backstage backend proxy so `LARAPILOT_API_TOKEN` stays server-side; the API is dev/staging only and never answers in production.';

        return $instructions;
    }

    /**
     * Specs whose code is safe to embed in a generated file path, keyed by
     * code. A hand-edited `backlog.yaml` could otherwise steer page writes
     * outside the docs directory.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function publishableSpecs(): array
    {
        $specs = [];

        foreach ($this->specs->allSpecs() as $spec) {
            $code = (string) ($spec['code'] ?? '');

            if ($code !== '' && SpecCode::isValid($code)) {
                $specs[$code] = $spec;
            }
        }

        return $specs;
    }

    /**
     * Drop empty entity fields so the descriptor carries only what Backstage
     * can act on (an empty `system` or `providesApis` is noise, not data).
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function prune(array $values): array
    {
        return array_filter(
            $values,
            fn (mixed $value): bool => $value !== null && $value !== [] && $value !== ''
        );
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    protected function refFor(array $entity): string
    {
        $metadata = is_array($entity['metadata'] ?? null) ? $entity['metadata'] : [];

        return strtolower((string) ($entity['kind'] ?? 'component'))
            .':'.(string) ($metadata['namespace'] ?? 'default')
            .'/'.(string) ($metadata['name'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array{code: string, title: string}|null
     */
    protected function epicOf(array $spec): ?array
    {
        $epic = $spec['epic'] ?? null;

        if (! is_array($epic) || ! isset($epic['code'])) {
            return null;
        }

        return [
            'code' => (string) $epic['code'],
            'title' => (string) ($epic['title'] ?? $epic['code']),
        ];
    }

    /**
     * Backstage entity names: lowercase alphanumerics plus `-`, `_`, `.`,
     * starting and ending alphanumeric, max 63 characters.
     */
    protected function normalizeName(string $value): string
    {
        $slug = Str::slug($value);

        if ($slug === '') {
            $slug = 'laravel-app';
        }

        return trim(substr($slug, 0, 63), '-_.');
    }

    protected function absoluteOption(?string $option, string $fallback): string
    {
        return is_string($option) && trim($option) !== ''
            ? $this->config->absolutePath(trim($option))
            : $fallback;
    }

    /**
     * Path of `$target` relative to `$fromDirectory`, for Backstage `$text`
     * placeholders (resolved relative to the catalog file).
     */
    protected function relativeReference(string $fromDirectory, string $target): string
    {
        $segments = static fn (string $path): array => array_values(array_filter(
            explode('/', trim(str_replace('\\', '/', $path), '/')),
            fn (string $segment): bool => $segment !== ''
        ));

        $from = $segments($fromDirectory);
        $to = $segments($target);

        while ($from !== [] && $to !== [] && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        $path = str_repeat('../', count($from)).implode('/', $to);

        // Backstage placeholders must stay explicitly relative; a bare
        // `.larapilot/...` already starts with a dot but is not `../`.
        return str_starts_with($path, '../') ? $path : './'.$path;
    }

    protected function prdSummary(): ?string
    {
        $prd = $this->prd->read();

        if ($prd === null) {
            return null;
        }

        $aliases = array_map('strtolower', ArtifactSections::prd()['Elevator Pitch']);
        $capturing = false;
        $buffer = [];

        foreach (preg_split('/\r?\n/', $prd) ?: [] as $line) {
            if (preg_match('/^#{1,6}\s+(.+?)\s*$/', $line, $matches) === 1) {
                if ($capturing) {
                    break;
                }

                $capturing = in_array(strtolower(trim($matches[1])), $aliases, true);

                continue;
            }

            if (! $capturing) {
                continue;
            }

            if (trim($line) === '') {
                if ($buffer !== []) {
                    break;
                }

                continue;
            }

            $buffer[] = trim($line);
        }

        $text = trim(implode(' ', $buffer));
        $text = trim((string) preg_replace('/^[-*]\s+/', '', $text));
        $text = trim((string) preg_replace('/[*_`>]+/', '', $text));

        return $text === '' ? null : Str::limit($text, 240);
    }

    protected function composerDescription(): ?string
    {
        $path = base_path('composer.json');

        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return null;
        }

        $description = trim((string) ($decoded['description'] ?? ''));

        return $description === '' ? null : $description;
    }

    protected function generatedNotice(): string
    {
        return '!!! note "Generated by Larapilot"'."\n"
            .'    Built from `.larapilot/` by `php artisan larapilot:backstage-export --write`. Edit the Larapilot artifacts, not this page.';
    }

    /**
     * @param  list<string>  $lines
     */
    protected function page(array $lines): string
    {
        return rtrim(implode("\n", $lines))."\n";
    }

    protected function escapeCell(string $value): string
    {
        return str_replace(['|', "\n"], ['\\|', ' '], trim($value));
    }

    protected function number(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.') ?: '0';
    }
}
