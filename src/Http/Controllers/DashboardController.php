<?php

declare(strict_types=1);

namespace Larapilot\Http\Controllers;

use Illuminate\Contracts\View\View;
use Larapilot\Services\ConfigService;
use Larapilot\Services\DashboardService;
use Larapilot\Support\SpecCode;

class DashboardController
{
    public function __construct(
        protected ConfigService $config,
        protected DashboardService $dashboard,
    ) {}

    public function index(): View
    {
        $this->guard();

        return view('larapilot::dashboard.index', $this->dashboard->board());
    }

    public function prd(): View
    {
        $this->guard();

        $prd = $this->dashboard->prd();

        return view('larapilot::dashboard.prd', [
            'prd' => $prd,
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

        return view('larapilot::dashboard.spec', $data);
    }

    protected function guard(): void
    {
        if (! $this->config->dashboardBrowsable()) {
            abort(404);
        }
    }
}
