<?php

declare(strict_types=1);

namespace Larapilot\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Larapilot\Services\Auth\DashboardSession;
use Larapilot\Services\ConfigService;
use Larapilot\Services\DashboardAuthService;
use Symfony\Component\HttpFoundation\Response;

/**
 * The door of the Larapilot JSON API (`/larapilot/api/*`).
 *
 * The token — made on the API token page or with `larapilot:api-token`
 * and kept hashed in `.larapilot/auth.yaml`, or set as
 * `LARAPILOT_API_TOKEN` in `.env` — travels as a bearer token or in the
 * `X-Larapilot-Token` header. A request that carries one must carry the
 * right one.
 *
 *   - Once a token exists, every request needs it, reads and writes, in
 *     every environment — except a read from the browser of a user signed
 *     in on the dashboard whose role sees the API reference.
 *   - With no token, a developer's machine (`local`, `testing`) is open,
 *     as the dashboard is, unless the `api_auth` setting is ON. Anywhere
 *     else the API is closed (503) until a token is made, and says how.
 *   - In production the API is hidden, as the dashboard is, unless
 *     `LARAPILOT_FORCE_IN_PROD` is true.
 */
class EnsureApiAuthorized
{
    public function __construct(
        protected ConfigService $config,
        protected DashboardAuthService $users,
        protected DashboardSession $session,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $policy = $this->session->policy();

        if (! $policy->isOpen() && ! $policy->requiresSignIn()) {
            abort(404);
        }

        $provided = $request->bearerToken() ?? $request->header('X-Larapilot-Token');

        if (is_string($provided) && $provided !== '') {
            if (! $this->matches($provided)) {
                abort(401, 'Invalid Larapilot API token.');
            }

            return $next($request);
        }

        $configured = $this->tokenConfigured();

        // The reference of the API — its page and its OpenAPI document —
        // describes the endpoints and holds no data of the project: once a
        // token exists, whoever sees the dashboard reads it, as the sidebar
        // links it, and sends the token from there. With none yet, the
        // page that says how to make one is more use.
        if ($configured && $request->isMethodSafe() && $request->routeIs('larapilot.api.docs', 'larapilot.api.openapi')
            && ($policy->isOpen() || ($this->session->user() !== null && $this->session->allows('api')))) {
            return $next($request);
        }

        if (! $configured && $policy->isOpen() && ! $this->config->apiAuthEnabled()) {
            return $next($request);
        }

        // A signed-in reader of the API reference, from the browser: the
        // session is the credential. Never for a write — this group has no CSRF.
        if ($request->isMethodSafe() && ! $policy->isOpen() && $this->session->user() !== null && $this->session->allows('api')) {
            return $next($request);
        }

        if (! $configured) {
            return $this->tokenMissing($request);
        }

        abort(401, 'Missing Larapilot API token.');
    }

    protected function matches(string $provided): bool
    {
        $env = (string) config('larapilot.api.token', '');

        if ($env !== '' && hash_equals($env, $provided)) {
            return true;
        }

        return $this->users->apiTokenMatches($provided);
    }

    protected function tokenConfigured(): bool
    {
        return (string) config('larapilot.api.token', '') !== '' || $this->users->apiToken() !== null;
    }

    /**
     * The API is asked for a token and the server has none yet: it stays
     * closed and says what opens it. A browser that asks for a page — the
     * API docs — gets the explanation as a page; every other client as JSON.
     */
    protected function tokenMissing(Request $request): Response
    {
        if (in_array('text/html', $request->getAcceptableContentTypes(), true)) {
            return response()->view('larapilot::dashboard.protected', ['area' => 'api'], 503);
        }

        return response()->json([
            'message' => 'The Larapilot API asks for a token here — APP_ENV is '.app()->environment().($this->config->apiAuthEnabled() ? ' and the api_auth setting is ON' : ', not a developer\'s machine').' — and none exists yet. '
                .'Make one on the API token page of the dashboard (an admin), with php artisan larapilot:api-token generate, or set LARAPILOT_API_TOKEN in .env; '
                .'then send it as "Authorization: Bearer <token>" or in the X-Larapilot-Token header.',
        ], 503);
    }
}
