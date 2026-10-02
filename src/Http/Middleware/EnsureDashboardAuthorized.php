<?php

declare(strict_types=1);

namespace Larapilot\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Larapilot\Services\ConfigService;
use Larapilot\Services\DashboardAuthService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional HTTP Basic Auth for the `/larapilot` dashboard UI.
 *
 * Disabled by default: when the `dashboard_auth` project setting is OFF this
 * middleware is a pass-through and the dashboard behaves exactly as before.
 * When ON, every dashboard request must carry valid Basic Auth credentials
 * from `.larapilot/auth.yaml`. This gate is never wired onto the JSON API
 * (`/larapilot/api/*`, guarded by `LARAPILOT_API_TOKEN`) or the MCP server.
 *
 * With the setting ON and no user created yet the dashboard fails closed
 * (HTTP 503) on a page that explains the setting and how to add the first user.
 */
class EnsureDashboardAuthorized
{
    public function __construct(
        protected ConfigService $config,
        protected DashboardAuthService $auth,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->config->dashboardAuthEnabled()) {
            return $next($request);
        }

        if (! $this->auth->hasUsers()) {
            return $this->firstUserMissing($request);
        }

        $realm = str_replace('"', '', (string) config('larapilot.dashboard_route.auth.realm', 'Larapilot'));
        $maxAttempts = max(0, (int) config('larapilot.dashboard_route.auth.max_attempts', 30));
        $key = 'larapilot-dashboard-auth:'.sha1((string) $request->ip());

        if ($maxAttempts > 0 && RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            abort(429, 'Too many failed sign-in attempts. Try again in '.RateLimiter::availableIn($key).'s.');
        }

        if ($this->auth->validate($request->getUser(), $request->getPassword())) {
            if ($maxAttempts > 0) {
                RateLimiter::clear($key);
            }

            return $next($request);
        }

        if ($maxAttempts > 0) {
            RateLimiter::hit($key, 60);
        }

        return response('Authentication required.', 401, [
            'WWW-Authenticate' => sprintf('Basic realm="%s", charset="UTF-8"', $realm),
        ]);
    }

    /**
     * The setting is ON and nobody can sign in yet: the dashboard stays closed
     * and says what opens it, instead of failing with a bare server error.
     */
    protected function firstUserMissing(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'The Larapilot dashboard is protected by a sign-in (the dashboard_auth project setting is ON) and no user has been created yet. '
                    .'Create the first one with: php artisan larapilot:dashboard-user add <username> — '
                    .'or turn the sign-in off with: php artisan larapilot:settings-set --dashboard-auth=NO',
            ], 503);
        }

        return response()->view('larapilot::dashboard.protected', ['area' => 'dashboard'], 503);
    }
}
