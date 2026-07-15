<?php

declare(strict_types=1);

namespace Larapilot\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Larapilot\Services\ConfigService;
use Larapilot\Services\DashboardService;
use Larapilot\Services\InternalFeedbackService;
use Larapilot\Services\SpecService;
use Larapilot\Support\SpecCode;

class DashboardController
{
    public function __construct(
        protected ConfigService $config,
        protected DashboardService $dashboard,
        protected SpecService $specs,
        protected InternalFeedbackService $feedback,
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

    protected function guard(): void
    {
        if (! $this->config->dashboardBrowsable()) {
            abort(404);
        }
    }
}
