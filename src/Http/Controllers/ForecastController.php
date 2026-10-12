<?php

declare(strict_types=1);

namespace Larapilot\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Larapilot\Http\Controllers\Concerns\ScopesVersions;
use Larapilot\Services\ConfigService;
use Larapilot\Services\ForecastService;
use Larapilot\Services\VersionService;

/**
 * The Forecast page: the story points of the backlog, the sprints one
 * person needs for them, and what the same work takes with Larapilot —
 * for the whole backlog, or the versions in scope.
 */
class ForecastController
{
    use ScopesVersions;

    public function __construct(
        protected ConfigService $config,
        protected ForecastService $forecast,
        protected VersionService $versions,
    ) {}

    public function index(Request $request): View
    {
        $this->guard();

        return view('larapilot::dashboard.forecast', $this->forecast->dashboard($this->selected($request)));
    }

    public function report(Request $request): Response
    {
        $this->guard();

        return response($this->forecast->reportMarkdown($this->forecast->snapshot($this->selected($request))), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="larapilot-forecast-report.md"',
        ]);
    }

    /**
     * @return list<string>
     */
    protected function selected(Request $request): array
    {
        $query = $this->scopedQuery($request, $this->versions);

        return isset($query['version']) ? $this->versions->selection($query['version']) : [];
    }

    protected function guard(): void
    {
        if (! $this->config->dashboardBrowsable()) {
            abort(404);
        }
    }
}
