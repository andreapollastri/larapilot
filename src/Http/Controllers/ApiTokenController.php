<?php

declare(strict_types=1);

namespace Larapilot\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Larapilot\Services\Auth\AuthAuditService;
use Larapilot\Services\Auth\DashboardSession;
use Larapilot\Services\ConfigService;
use Larapilot\Services\DashboardAuthService;

/**
 * The token of the JSON API, for admins: made here, shown once, kept
 * hashed; rotated or revoked here too.
 */
class ApiTokenController
{
    public function __construct(
        protected ConfigService $config,
        protected DashboardSession $session,
        protected DashboardAuthService $users,
        protected AuthAuditService $audit,
    ) {}

    public function index(): View
    {
        $this->guard();

        return view('larapilot::dashboard.api-token', [
            'token' => $this->users->apiToken(),
            'env_token' => (string) config('larapilot.api.token', '') !== '',
            'fresh' => session()->get('larapilot_api_token'),
            'api_auth' => $this->config->apiAuthEnabled(),
            'mode' => $this->session->policy()->mode(),
            'path' => $this->config->relativePath($this->users->path()),
            'docs_url' => route('larapilot.api.docs'),
            'example_url' => route('larapilot.api.board'),
        ]);
    }

    public function issue(): RedirectResponse
    {
        $this->guard();

        $had = $this->users->apiToken() !== null;
        $token = $this->users->issueApiToken($this->session->name() ?? 'dashboard');
        $this->audit->record($had ? 'token.rotated' : 'token.generated', ['actor' => $this->session->name() ?? 'dashboard']);

        return redirect()->route('larapilot.dashboard.api-token')
            ->with('larapilot_api_token', $token)
            ->with('larapilot_success', $had ? 'The token is rotated: the one there was no longer works. Copy the new one now.' : 'The token is made. Copy it now: it is not shown again.');
    }

    public function revoke(): RedirectResponse
    {
        $this->guard();

        if (! $this->users->revokeApiToken()) {
            return redirect()->route('larapilot.dashboard.api-token')->with('larapilot_error', 'There is no token to revoke.');
        }

        $this->audit->record('token.revoked', ['actor' => $this->session->name() ?? 'dashboard']);

        return redirect()->route('larapilot.dashboard.api-token')->with('larapilot_success', 'The token is revoked. Nothing answers to it any more.');
    }

    protected function guard(): void
    {
        if (! $this->config->dashboardBrowsable()) {
            abort(404);
        }
    }
}
