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
        protected MockupService $mockups,
        protected InternalFeedbackService $feedback,
        protected ChoicesService $choices,
        protected UsageService $usageService,
        protected DecisionService $decisions,
        protected GitService $git,
        protected CustomSkillService $customSkills,
        protected EconomicsService $economicsService,
    ) {}

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    protected function enrichSpec(array $spec): array
    {
        $code = (string) ($spec['code'] ?? '');

        return array_merge($spec, [
            'tasks' => $code !== '' ? $this->plans->taskProgress($code) : ['total' => 0, 'done' => 0],
            'mockups' => $code !== '' ? $this->mockups->summary($code) : ['available' => false, 'screen_count' => 0],
            'feedback' => $code !== '' ? $this->feedback->summary($code, $spec) : ['enabled' => false, 'available' => false, 'entry_count' => 0, 'blocking_count' => 0, 'writable' => false, 'path' => ''],
        ]);
    }

    /**
     * Board data with raw (un-enriched) specs, shared by the dashboard and
     * the API so each surface enriches specs exactly once.
     *
     * @return array{metrics: array<string, mixed>, columns: array<string, array<int, array<string, mixed>>>, statusOrder: list<string>}
     */
    public function rawBoard(): array
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
            'metrics' => array_merge($this->specs->metrics(), $this->plans->metrics()),
            'columns' => $columns,
            // include columns created for statuses outside the configured
            // workflow, so those specs still show on the board
            'statusOrder' => array_map('strval', array_keys($columns)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function board(): array
    {
        $board = $this->rawBoard();

        foreach ($board['columns'] as $status => $specs) {
            $board['columns'][$status] = array_map(
                fn (array $spec): array => $this->enrichSpec($spec),
                $specs
            );
        }

        return $board;
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
            'mockups' => $this->mockups->forSpec($code),
            'spec_html' => Markdown::toHtml((string) ($data['spec']['body'] ?? '')),
            'plan_html' => is_array($plan)
                ? Markdown::toHtml((string) ($plan['plan_body'] ?? ''))
                : null,
            'feedback' => $this->feedback->forSpec($code, $data['spec']),
            'decisions' => $this->decisions($code),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function decisions(?string $specCode = null): array
    {
        $payload = $this->decisions->forView($specCode);

        if ($specCode === null && is_array($payload['groups'] ?? null)) {
            $payload['groups'] = array_map(function (array $group): array {
                $code = $group['spec_code'] ?? null;

                if (! is_string($code) || $code === '') {
                    return $group;
                }

                $spec = $this->specs->find($code);
                $title = is_array($spec) ? trim((string) ($spec['title'] ?? '')) : '';

                $group['label'] = $title !== '' ? "{$code} — {$title}" : $code;

                return $group;
            }, $payload['groups']);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $data = $this->choices->dashboard();

        return [
            'settings' => $data['settings'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function inception(): array
    {
        $data = $this->choices->dashboard();

        return [
            'inception' => $data['inception'],
            'path' => $data['path'],
            'updated_at' => is_string($data['raw']['updated_at'] ?? null) ? $data['raw']['updated_at'] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function docs(): array
    {
        return [
            'settings' => $this->config->settings(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function skills(): array
    {
        $this->customSkills->registerAll();

        return [
            'skills' => $this->customSkills->list(),
            'directory' => $this->customSkills->directory(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function usage(): array
    {
        return $this->usageService->dashboard();
    }

    /**
     * @return array<string, mixed>
     */
    public function git(?string $authorEmail = null): array
    {
        return $this->git->contributionActivity($authorEmail);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function economics(array $overrides = []): array
    {
        return $this->economicsService->dashboard($overrides);
    }

    /**
     * @return array<string, mixed>
     */
    public function design(): array
    {
        $prd = $this->prd->read();
        $catalog = $this->mockups->catalog();

        return [
            'catalog' => $catalog,
            'project_title' => $this->projectTitle($prd),
            'presentation_url' => $this->routeIfAvailable('larapilot.dashboard.design.presentation'),
            'package_url' => $this->routeIfAvailable('larapilot.dashboard.design.package'),
        ];
    }

    protected function projectTitle(?string $prd): string
    {
        if (is_string($prd) && preg_match('/^#\s+(.+)$/m', $prd, $matches) === 1) {
            $title = trim($matches[1]);

            if ($title !== '') {
                return $title;
            }
        }

        return 'Larapilot';
    }

    protected function routeIfAvailable(string $name): ?string
    {
        if (! app('router')->has($name)) {
            return null;
        }

        return route($name, absolute: false);
    }
}
