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
        protected MockupService $mockups,
        protected InternalFeedbackService $feedback,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function board(): array
    {
        $board = $this->dashboard->board();

        $columns = [];

        foreach ($board['columns'] as $status => $specs) {
            $columns[$status] = array_map(
                fn (array $item): array => $this->enrichSpecSummary(
                    $this->specPayloadFromBoardItem($item)
                ),
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

        return array_merge($spec, [
            'task_progress' => $code !== '' ? $this->plans->taskProgress($code) : ['total' => 0, 'done' => 0],
            'mockups' => $code !== '' ? $this->mockupsForApi($code) : $this->emptyMockups(),
            'feedback' => $code !== '' ? $this->feedback->summary($code, $spec) : $this->emptyFeedback(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function specPayloadFromBoardItem(array $item): array
    {
        unset($item['tasks'], $item['mockups'], $item['feedback']);

        return $item;
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
            'entries' => [],
        ];
    }
}
