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
            abort(500, 'Larapilot dashboard auth is enabled but no users are configured. Run: php artisan larapilot:dashboard-user add <username>');
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
}
