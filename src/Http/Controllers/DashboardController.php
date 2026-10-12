<?php

declare(strict_types=1);

namespace Larapilot\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Larapilot\Http\Controllers\Concerns\ScopesVersions;
use Larapilot\Services\BoardViewService;
use Larapilot\Services\ConfigService;
use Larapilot\Services\DashboardExportService;
use Larapilot\Services\DashboardService;
use Larapilot\Services\DecisionService;
use Larapilot\Services\EconomicsService;
use Larapilot\Services\FunctionalSummaryWriter;
use Larapilot\Services\GitGraphService;
use Larapilot\Services\InternalFeedbackService;
use Larapilot\Services\MockupPackageService;
use Larapilot\Services\MockupService;
use Larapilot\Services\PrdService;
use Larapilot\Services\SpecService;
use Larapilot\Services\VersionService;
use Larapilot\Support\SpecCode;

class DashboardController
{
    use ScopesVersions;

    public function __construct(
        protected ConfigService $config,
        protected DashboardService $dashboard,
        protected SpecService $specs,
        protected InternalFeedbackService $feedback,
        protected EconomicsService $economics,
        protected FunctionalSummaryWriter $summary,
        protected PrdService $prd,
        protected MockupPackageService $mockupPackage,
        protected MockupService $mockups,
        protected DecisionService $decisions,
        protected DashboardExportService $exports,
        protected BoardViewService $views,
        protected VersionService $versions,
    ) {}

    public function index(Request $request): View
    {
        $this->guard();

        $board = $this->dashboard->board();
        $flat = [];

        foreach ($board['columns'] as $specs) {
            foreach ($specs as $spec) {
                $flat[] = $spec;
            }
        }

        $view = $this->views->resolve($this->scopedQuery($request, $this->versions), $flat);

        foreach ($board['columns'] as $status => $specs) {
            $board['columns'][$status] = array_values(array_filter(
                array_map(fn (array $spec): array => $this->views->annotate($spec, $view), $specs),
                fn (array $spec): bool => $this->views->matches((string) ($spec['code'] ?? ''), $view)
            ));
        }

        $board['backlog_total'] = (int) ($board['metrics']['total'] ?? 0);

        // Scoped to some versions, the figures above the board are theirs.
        if ($view['selected'] !== []) {
            $board['metrics'] = $this->scopedMetrics($board);
        }

        $columns = $view['group'] === 'status' ? null : $this->views->columns($board['columns'], $view);

        if ($view['filtered'] && is_array($columns)) {
            $columns = array_values(array_filter($columns, static fn (array $column): bool => $column['items'] !== []));
        }

        $board['view'] = $view;
        $board['viewColumns'] = $columns;
        $board['scope'] = ['versions' => $view['versions'], 'selected' => $view['selected']];

        return view('larapilot::dashboard.index', $board);
    }

    public function boardDownload(Request $request): Response
    {
        $this->guard();

        return $this->markdown($this->exports->board($this->scopedQuery($request, $this->versions)), $this->exports->boardFilename());
    }

    /**
     * Total, done, completion, and work in progress of the specs left on
     * the board.
     *
     * @param  array<string, mixed>  $board
     * @return array<string, mixed>
     */
    protected function scopedMetrics(array $board): array
    {
        $done = strtoupper((string) ($board['workflow']['done'] ?? $this->config->status('done')));
        $wip = array_map('strtoupper', is_array($board['workflow']['wip'] ?? null) ? $board['workflow']['wip'] : []);
        $metrics = $board['metrics'];
        $metrics['total'] = 0;
        $metrics['done'] = 0;
        $metrics['wip'] = 0;

        foreach ($board['columns'] as $status => $specs) {
            $metrics['total'] += count($specs);
            $metrics['done'] += strtoupper((string) $status) === $done ? count($specs) : 0;
            $metrics['wip'] += in_array(strtoupper((string) $status), $wip, true) ? count($specs) : 0;
        }

        $metrics['completion_rate'] = $metrics['total'] > 0 ? round($metrics['done'] / $metrics['total'] * 100, 1) : 0;

        return $metrics;
    }

    /**
     * Epics, their stories, and the tasks of each, with the story points.
     */
    public function epicsDownload(Request $request): Response
    {
        $this->guard();

        return $this->markdown($this->exports->epics($this->scopedQuery($request, $this->versions)), $this->exports->epicsFilename());
    }

    public function prd(): View
    {
        $this->guard();

        $prd = $this->dashboard->prd();

        return view('larapilot::dashboard.prd', [
            'prd' => $prd,
            'decisions' => $this->dashboard->decisions(),
            'summaryLabel' => is_array($prd) ? $this->summary->label((string) $prd['content']) : null,
        ]);
    }

    public function prdDownload(): Response
    {
        $this->guard();

        $content = $this->exports->prd();

        if ($content === null) {
            abort(404);
        }

        return $this->markdown($content, $this->exports->prdFilename());
    }

    public function functionalSummary(): Response
    {
        $this->guard();

        $content = $this->prd->read();

        if ($content === null || trim($content) === '') {
            abort(404);
        }

        return response($this->summary->render($content), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$this->summary->filename($content).'"',
        ]);
    }

    public function settings(): View
    {
        $this->guard();

        return view('larapilot::dashboard.settings', $this->dashboard->settings());
    }

    public function inception(): View
    {
        $this->guard();

        return view('larapilot::dashboard.inception', $this->dashboard->inception());
    }

    public function docs(): View
    {
        $this->guard();

        return view('larapilot::dashboard.docs', $this->dashboard->docs());
    }

    public function skills(): View
    {
        $this->guard();

        return view('larapilot::dashboard.skills', $this->dashboard->skills());
    }

    public function skill(Request $request, string $name): View
    {
        $this->guard();

        $skill = $this->dashboard->skill($name, $this->from($request));

        if ($skill === null) {
            abort(404);
        }

        return view('larapilot::dashboard.skill', ['skill' => $skill]);
    }

    public function skillDownload(Request $request, string $name): Response
    {
        $this->guard();

        $skill = $this->dashboard->skill($name, $this->from($request));

        if ($skill === null) {
            abort(404);
        }

        return $this->markdown($skill['content'], $skill['folder'].'-SKILL.md');
    }

    public function guideline(string $id): View
    {
        $this->guard();

        $guideline = $this->dashboard->guideline($id);

        if ($guideline === null) {
            abort(404);
        }

        return view('larapilot::dashboard.guideline', ['guideline' => $guideline]);
    }

    /**
     * Which skill is meant, among the ones that carry one name.
     */
    protected function from(Request $request): ?string
    {
        $from = $request->query('from');

        return is_string($from) && $from !== '' ? $from : null;
    }

    public function usage(): View
    {
        $this->guard();

        return view('larapilot::dashboard.usage', $this->dashboard->usage());
    }

    public function plan(Request $request): View
    {
        $this->guard();

        $plan = $this->dashboard->plan();
        $specs = $this->specs->allSpecs();
        $view = $this->views->resolve($this->scopedQuery($request, $this->versions), $specs, 'epic');
        $annotated = array_values(array_filter(
            array_map(fn (array $spec): array => $this->views->annotate($spec, $view), $specs),
            fn (array $spec): bool => $this->views->matches((string) ($spec['code'] ?? ''), $view)
        ));
        $bands = $view['group'] === 'epic' ? [] : $this->views->columns(['ALL' => $annotated], $view);

        if ($view['filtered']) {
            $bands = array_values(array_filter($bands, static fn (array $column): bool => $column['items'] !== []));
            $kept = [];

            foreach ($annotated as $spec) {
                $kept[strtoupper((string) ($spec['code'] ?? ''))] = true;
            }

            if (is_array($plan['gantt']['bars'] ?? null)) {
                $plan['gantt']['bars'] = array_values(array_filter(
                    $plan['gantt']['bars'],
                    static function (array $bar) use ($kept): bool {
                        if (($bar['type'] ?? '') === 'epic') {
                            foreach ((array) ($bar['spec_codes'] ?? []) as $code) {
                                if (isset($kept[strtoupper((string) $code)])) {
                                    return true;
                                }
                            }

                            return false;
                        }

                        $spec = strtoupper((string) strtok((string) ($bar['id'] ?? ''), '·'));

                        return $spec !== '' && isset($kept[$spec]);
                    }
                ));
            }
        }

        // A milestone tied to a version out of scope is not this plan's.
        $versionsInScope = array_values(array_diff($view['selected'], [VersionService::NONE]));

        if ($view['selected'] !== [] && is_array($plan['gantt']['milestones'] ?? null)) {
            $plan['gantt']['milestones'] = array_values(array_filter(
                $plan['gantt']['milestones'],
                static fn (array $milestone): bool => trim((string) ($milestone['release'] ?? '')) === ''
                    || in_array(ltrim((string) $milestone['release'], 'vV'), $versionsInScope, true)
            ));
        }

        $plan['view'] = $view;
        $plan['bands'] = $bands;
        $plan['scope'] = ['versions' => $view['versions'], 'selected' => $view['selected']];

        return view('larapilot::dashboard.plan', $plan);
    }

    /**
     * Epics, stories, tasks, milestones, and the forecast, as Markdown.
     */
    public function planDownload(): Response
    {
        $this->guard();

        return $this->markdown($this->exports->plan(), $this->exports->planFilename());
    }

    public function economics(Request $request): View
    {
        $this->guard();

        return view('larapilot::dashboard.economics', $this->dashboard->economics($this->pricingOverrides($request)));
    }

    /**
     * Everything the pricing tool recomputes, rendered on its own so a changed
     * dropdown swaps the panel instead of reloading the page.
     */
    public function economicsPanel(Request $request): View
    {
        $this->guard();

        return view('larapilot::dashboard.partials.economics-panel', $this->dashboard->economics($this->pricingOverrides($request)));
    }

    public function economicsReport(Request $request): Response
    {
        $this->guard();

        return response($this->economics->reportMarkdown($this->pricingOverrides($request)), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="larapilot-economics.md"',
        ]);
    }

    public function economicsQuote(Request $request): Response
    {
        $this->guard();

        return response($this->economics->quoteMarkdown($this->pricingOverrides($request)), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$this->economics->quoteFilename().'"',
        ]);
    }

    /**
     * Query-string what-ifs for the Economics pricing tool. The service keeps
     * only the keys it knows and refuses anything outside their range, so an
     * edited URL can move the simulation and nothing else.
     *
     * @return array<string, mixed>
     */
    protected function pricingOverrides(Request $request): array
    {
        return $this->economics->normalizeOverrides($request->query());
    }

    public function security(Request $request): View
    {
        $this->guard();

        return view('larapilot::dashboard.security', $this->dashboard->security($request->boolean('refresh')));
    }

    public function securityReport(): Response
    {
        $this->guard();

        $report = $this->dashboard->securityReport();

        if ($report === null) {
            abort(404);
        }

        return $this->markdown($report, 'security-report-'.now()->format('Y-m-d').'.md');
    }

    public function securityRegister(): Response
    {
        $this->guard();

        $register = $this->dashboard->securityRegister();

        if ($register === null) {
            abort(404);
        }

        return $this->markdown($register['content'], $register['filename']);
    }

    public function chooseSecurityRepository(Request $request): RedirectResponse
    {
        $this->guard();

        $provider = $this->config->securityProvider();

        if ($provider === '') {
            abort(404);
        }

        $validated = $request->validate([
            'repository' => $provider === 'aikido' ? ['required', 'integer', 'min:1'] : ['required', 'string', 'uuid'],
        ]);

        try {
            $repository = $this->dashboard->chooseSecurityRepository((string) $validated['repository']);
        } catch (\Throwable $e) {
            return redirect()
                ->route('larapilot.dashboard.security')
                ->with('larapilot_error', $e->getMessage());
        }

        return redirect()
            ->route('larapilot.dashboard.security', ['refresh' => 1])
            ->with('larapilot_success', 'This project is “'.$repository['name'].'” in '.($provider === 'hackly' ? 'Hackly' : 'Aikido').'. The choice is in .larapilot/'.$provider.'.yaml: commit it.');
    }

    public function errors(Request $request): View
    {
        $this->guard();

        return view('larapilot::dashboard.errors', $this->dashboard->errors($request->boolean('refresh')));
    }

    public function errorsReport(): Response
    {
        $this->guard();

        $report = $this->dashboard->errorsReport();

        if ($report === null) {
            abort(404);
        }

        return $this->markdown($report, $this->dashboard->errorsReportFilename());
    }

    public function design(): View
    {
        $this->guard();

        return view('larapilot::dashboard.design', $this->dashboard->design());
    }

    public function designPresentation(): Response
    {
        $this->guard();

        return response($this->mockupPackage->presentationHtml(false), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    public function chooseMockupStyle(Request $request, string $code): RedirectResponse
    {
        $this->guard();

        if (! SpecCode::isValid($code)) {
            abort(404);
        }

        $validated = $request->validate([
            'style' => ['required', 'string', 'max:42', 'regex:/^[a-z0-9][a-z0-9-]{0,40}$/'],
        ]);

        try {
            $manifest = $this->mockups->chooseStyle($code, $validated['style']);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('larapilot.dashboard.design')
                ->with('larapilot_error', $e->getMessage());
        }

        $label = $validated['style'];

        foreach ($manifest['styles'] as $row) {
            if (($row['id'] ?? '') === $validated['style']) {
                $label = (string) ($row['label'] ?? $label);
                break;
            }
        }

        if ($this->config->decisionLogEnabled()) {
            $this->decisions->log([
                'topic' => 'mockup style',
                'value' => $validated['style'],
                'label' => 'Mockup style for '.$code,
                'rationale' => $label,
                'source' => 'askquestion',
                'skill' => 'larapilot-design',
                'spec' => $code,
            ]);
        }

        return redirect()
            ->route('larapilot.dashboard.design')
            ->with('larapilot_success', 'Style “'.$label.'” is now the direction for '.$code.'.');
    }

    public function designPackage(): Response
    {
        $this->guard();

        $path = $this->mockupPackage->writeZip();
        $contents = (string) file_get_contents($path);
        $filename = $this->mockupPackage->downloadFilename();

        if (is_file($path)) {
            unlink($path);
        }

        return response($contents, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function git(Request $request): View
    {
        $this->guard();

        $author = $request->query('author', '');
        $author = is_string($author) ? trim($author) : '';
        $commits = $request->query('commits');

        return view('larapilot::dashboard.git', $this->dashboard->git(
            $author !== '' ? $author : null,
            is_numeric($commits) ? (int) $commits : GitGraphService::DEFAULT_LIMIT
        ));
    }

    public function usageReport(): Response
    {
        $this->guard();

        $data = $this->dashboard->usage();

        return response((string) ($data['report_markdown'] ?? ''), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="larapilot-usage-report.md"',
        ]);
    }

    public function spec(string $code): View
    {
        $this->guard();

        if (! SpecCode::isValid($code)) {
            abort(404);
        }

        $data = $this->dashboard->spec($code);

        if ($data === null) {
            abort(404);
        }

        // The version the story targets, and how far that version is.
        $data['version'] = null;

        if ($this->versions->enabled()) {
            $target = $this->versions->assignments()[(string) ($data['spec']['code'] ?? $code)] ?? null;

            foreach ($target !== null ? $this->versions->rows(null, false) : [] as $row) {
                if ($row['version'] === $target) {
                    $data['version'] = $row;
                }
            }
        }

        return view('larapilot::dashboard.spec', $data);
    }

    public function specDownload(string $code): Response
    {
        $this->guard();

        if (! SpecCode::isValid($code)) {
            abort(404);
        }

        $content = $this->exports->spec($code);

        if ($content === null) {
            abort(404);
        }

        return $this->markdown($content, $this->exports->specFilename($code));
    }

    public function storeComment(Request $request, string $code): RedirectResponse
    {
        $this->guard();

        if (! SpecCode::isValid($code)) {
            abort(404);
        }

        if (! $this->config->commentsEnabled()) {
            abort(404);
        }

        $spec = $this->specs->find($code);

        if ($spec === null) {
            abort(404);
        }

        if (! $this->feedback->canComment($spec)) {
            return redirect()
                ->route('larapilot.dashboard.spec', $code)
                ->with('larapilot_error', 'Comments are closed for this user story.');
        }

        $validated = $request->validate([
            'author' => ['required', 'string', 'max:80'],
            'message' => ['required', 'string', 'max:10000'],
            'blocks_merge' => ['sometimes', 'boolean'],
        ]);

        $this->feedback->append(
            $code,
            $validated['author'],
            $validated['message'],
            strtoupper((string) ($spec['status'] ?? 'TODO')),
            (bool) ($validated['blocks_merge'] ?? false)
        );

        return redirect()
            ->route('larapilot.dashboard.spec', $code)
            ->with('larapilot_success', 'Comment added.');
    }

    protected function markdown(string $content, string $filename): Response
    {
        return response($content, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    protected function guard(): void
    {
        if (! $this->config->dashboardBrowsable()) {
            abort(404);
        }
    }
}
