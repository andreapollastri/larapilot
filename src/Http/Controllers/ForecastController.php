<?php

declare(strict_types=1);

namespace Larapilot\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Larapilot\Services\ConfigService;
use Larapilot\Services\ForecastService;

/**
 * The Forecast page: the story points of the backlog, the sprints one
 * person needs for them, and what the same work takes with Larapilot.
 */
class ForecastController
{
    public function __construct(
        protected ConfigService $config,
        protected ForecastService $forecast,
    ) {}

    public function index(): View
    {
        $this->guard();

        return view('larapilot::dashboard.forecast', $this->forecast->dashboard());
    }

    public function report(): Response
    {
        $this->guard();

        return response($this->forecast->reportMarkdown(), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="larapilot-forecast-report.md"',
        ]);
    }

    protected function guard(): void
    {
        if (! $this->config->dashboardBrowsable()) {
            abort(404);
        }
    }
}
