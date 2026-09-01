<?php

declare(strict_types=1);

namespace Larapilot\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Larapilot\Services\ConfigService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Token auth for the Larapilot JSON API (`/larapilot/api/*`).
 *
 * When `LARAPILOT_API_TOKEN` is set, every API request must carry it as a
 * bearer token or `X-Larapilot-Token` header — reads and writes alike.
 *
 * When no token is configured the behaviour depends on the `api_auth` project
 * setting:
 *   - `api_auth: true`  → the API fails closed (HTTP 503) until the token is set.
 *   - `api_auth: false` → (default) reads stay open in the allowed environments
 *     and mutating requests are only allowed in local-style environments,
 *     because CSRF is disabled on this route group.
 */
class EnsureApiAuthorized
{
    public function __construct(protected ConfigService $config) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('larapilot.api.token', '');

        if ($token !== '') {
            $provided = $request->bearerToken() ?? $request->header('X-Larapilot-Token');

            if (! is_string($provided) || $provided === '' || ! hash_equals($token, $provided)) {
                abort(401, 'Invalid or missing Larapilot API token.');
            }

            return $next($request);
        }

        if ($this->config->apiAuthEnabled()) {
            abort(503, 'Larapilot API auth is enabled (settings.api_auth) but LARAPILOT_API_TOKEN is not set. Configure the token, or run: php artisan larapilot:settings-set --api-auth=NO');
        }

        if (! $request->isMethodSafe() && ! app()->environment(['local', 'development', 'testing'])) {
            abort(403, 'Set LARAPILOT_API_TOKEN to allow Larapilot API writes outside local environments.');
        }

        return $next($request);
    }
}
