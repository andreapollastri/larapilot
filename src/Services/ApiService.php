<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\Markdown;

class ApiService
{
    /**
     * @var array<string, array{release: string|null, sprint: int|null, sprint_proposed: bool}>|null
     */
    protected ?array $viewAssignments = null;

    public function __construct(
        protected ConfigService $config,
        protected DashboardService $dashboard,
        protected SpecService $specs,
        protected PlanService $plans,
        protected PrdService $prd,
        protected MockupService $mockups,
        protected InternalFeedbackService $feedback,
        protected BoardViewService $views,
        protected VersionService $versions,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function board(): array
    {
        $board = $this->dashboard->rawBoard();

        $columns = [];

        foreach ($board['columns'] as $status => $specs) {
            $columns[$status] = array_map(
                fn (array $item): array => $this->enrichSpecSummary($item),
                $specs
            );
        }

        return [
            'metrics' => $board['metrics'],
            'status_order' => $board['statusOrder'],
            'columns' => $columns,
            'workflow' => $this->config->resolve()['workflow']['statuses'] ?? config('larapilot.workflow.statuses', []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function specs(?string $status = null, int $page = 1, int $perPage = 50, mixed $version = null): array
    {
        $result = $this->specs->list($status);
        $selected = $version !== null && $this->versions->enabled() ? $this->versions->selection($version) : [];

        $all = array_values($result['items']);

        // `?version=1.2.0,none`: the stories of those versions, or of none.
        if ($selected !== []) {
            $all = array_values(array_filter(
                $all,
                fn (array $spec): bool => in_array($this->viewAssignments()[(string) ($spec['code'] ?? '')]['release'] ?? VersionService::NONE, $selected, true)
            ));
        }

        $total = count($all);
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $totalPages = $total === 0 ? 1 : (int) ceil($total / $perPage);
        $page = min($page, $totalPages);

        $items = array_map(
            fn (array $spec): array => $this->enrichSpecSummary($spec),
            array_slice($all, ($page - 1) * $perPage, $perPage)
        );

        return [
            'status' => $status,
            'version' => $selected !== [] ? $selected : null,
            'count' => count($items),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'items' => $items,
            'summary' => $result['summary'],
        ];
    }

    /**
     * Every version with what it holds, what it has delivered, and its
     * forecast against its milestone; the version the work is on; the open
     * stories no version claims.
     *
     * @return array<string, mixed>
     */
    public function versions(): array
    {
        $enabled = $this->versions->enabled();
        $rows = $enabled ? $this->versions->rows() : [];

        return [
            'enabled' => $enabled,
            'release_mode' => $this->config->releaseModeEnabled(),
            'current' => $rows !== [] ? $this->versions->current() : null,
            'versions' => $rows,
            'unassigned' => $this->versions->unassigned(),
        ];
    }

    /**
     * One version with its stories, its epics, and its release notes.
     *
     * @return array<string, mixed>|null
     */
    public function version(string $version): ?array
    {
        try {
            return $this->versions->enabled() ? $this->versions->show($version) : null;
        } catch (\InvalidArgumentException) {
            return null;
        }
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
        $taskProgress = $this->plans->taskProgress($code);
        $mockups = $this->mockupsForApi($code);
        $feedback = $this->feedback->forSpec($code, $data['spec']);
        $spec = array_merge($data['spec'], [
            'task_progress' => $taskProgress,
            'mockups' => $mockups,
            'feedback' => $feedback,
        ]);

        return [
            'spec' => $spec,
            'plan' => is_array($plan)
                ? [
                    'code' => (string) ($plan['code'] ?? $code),
                    'plan_body' => (string) ($plan['plan_body'] ?? ''),
                    'updated_at' => $plan['updated_at'] ?? null,
                ]
                : null,
            'tasks' => $data['tasks'],
            'workdir' => $data['workdir'],
            'task_progress' => $taskProgress,
            'mockups' => $mockups,
            'feedback' => $feedback,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function storeComment(string $code, string $author, string $message, bool $blocksMerge = false): ?array
    {
        $spec = $this->specs->find($code);

        if ($spec === null) {
            return null;
        }

        $this->feedback->append(
            $code,
            $author,
            $message,
            strtoupper((string) ($spec['status'] ?? 'TODO')),
            $blocksMerge
        );

        $summary = $this->feedback->summary($code, $spec);
        $feedback = $this->feedback->forSpec($code, $spec);

        return [
            'code' => $code,
            'path' => $summary['path'],
            'entry_count' => $summary['entry_count'],
            'blocking_count' => $summary['blocking_count'],
            'feedback' => $feedback,
            'mockups' => $this->mockupsForApi($code),
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

        $assignment = $this->viewAssignments()[$code] ?? ['release' => null, 'sprint' => null, 'sprint_proposed' => false];

        return array_merge($spec, [
            'task_progress' => $code !== '' ? $this->plans->taskProgress($code) : ['total' => 0, 'done' => 0],
            'mockups' => $code !== '' ? $this->mockupsForApi($code) : $this->emptyMockups(),
            'feedback' => $code !== '' ? $this->feedback->summary($code, $spec) : $this->emptyFeedback(),
            'release' => $assignment['release'],
            'sprint' => $assignment['sprint'],
            'sprint_proposed' => $assignment['sprint_proposed'],
        ]);
    }

    /**
     * Release and sprint of every spec, computed once per request.
     *
     * @return array<string, array{release: string|null, sprint: int|null, sprint_proposed: bool}>
     */
    protected function viewAssignments(): array
    {
        if ($this->viewAssignments === null) {
            $view = $this->views->resolve([], $this->specs->allSpecs());
            $this->viewAssignments = $view['assignments'];
        }

        return $this->viewAssignments;
    }

    /**
     * @return array<string, mixed>
     */
    protected function mockupsForApi(string $code): array
    {
        return $this->absoluteMockupUrls($this->mockups->summary($code));
    }

    /**
     * @param  array<string, mixed>  $mockups
     * @return array<string, mixed>
     */
    protected function absoluteMockupUrls(array $mockups): array
    {
        if (isset($mockups['entry_url']) && is_string($mockups['entry_url']) && $mockups['entry_url'] !== '') {
            $mockups['entry_url'] = url($mockups['entry_url']);
        }

        if (isset($mockups['screens']) && is_array($mockups['screens'])) {
            foreach ($mockups['screens'] as $index => $screen) {
                if (! is_array($screen)) {
                    continue;
                }

                if (isset($screen['url']) && is_string($screen['url']) && $screen['url'] !== '') {
                    $mockups['screens'][$index]['url'] = url($screen['url']);
                }
            }
        }

        return $mockups;
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyMockups(): array
    {
        return [
            'available' => false,
            'screen_count' => 0,
            'screens' => [],
            'browsable' => $this->config->mockupsBrowsable(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyFeedback(): array
    {
        return [
            'enabled' => false,
            'available' => false,
            'entry_count' => 0,
            'blocking_count' => 0,
            'writable' => false,
            'path' => '',
        ];
    }
}
