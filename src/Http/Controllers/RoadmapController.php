<?php

declare(strict_types=1);

namespace Larapilot\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Larapilot\Services\ConfigService;
use Larapilot\Services\RoadmapService;

/**
 * The product roadmap: the page the team reads inside the dashboard, and
 * the one the outside reads at an address only the token opens.
 */
class RoadmapController
{
    public function __construct(
        protected ConfigService $config,
        protected RoadmapService $roadmap,
    ) {}

    /**
     * The team's page: what the public sees, and the state of the public link.
     */
    public function index(): View
    {
        $this->guard();

        $enabled = $this->roadmap->enabled();

        if ($enabled) {
            $this->roadmap->ensureToken();
        }

        return view('larapilot::dashboard.roadmap', $this->roadmap->build() + [
            'public' => [
                'enabled' => $enabled,
                'url' => $this->roadmap->publicUrl(),
                'path' => $this->config->relativePath($this->roadmap->path()),
                'left_out' => RoadmapService::LEFT_OUT,
            ],
        ]);
    }

    /**
     * A new token: the link that was shared stops answering.
     */
    public function regenerate(): RedirectResponse
    {
        $this->guard();

        if (! $this->roadmap->enabled()) {
            return redirect()->route('larapilot.dashboard.roadmap')
                ->with('larapilot_error', 'The public link is off: turn it on first with php artisan larapilot:settings-set --public-roadmap=YES.');
        }

        $this->roadmap->regenerateToken();

        return redirect()->route('larapilot.dashboard.roadmap')
            ->with('larapilot_success', 'New public link made. The old one answers 404 from now on.');
    }

    /**
     * The public page: served wherever the application runs, to whoever
     * holds the token, as long as the setting is on.
     */
    public function public(string $token): View
    {
        if (! $this->roadmap->enabled() || ! $this->roadmap->matchesToken($token)) {
            abort(404);
        }

        return view('larapilot::roadmap.public', $this->roadmap->build());
    }

    protected function guard(): void
    {
        if (! $this->config->dashboardBrowsable()) {
            abort(404);
        }
    }
}
