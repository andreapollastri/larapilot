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
 *   - `api_auth: true`  → the API fails closed (HTTP 503) until the token is
 *     set, with a message that explains the setting and how to add the token.
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
            return $this->tokenMissing($request);
        }

        if (! $request->isMethodSafe() && ! app()->environment(['local', 'development', 'testing'])) {
            abort(403, 'Set LARAPILOT_API_TOKEN to allow Larapilot API writes outside local environments.');
        }

        return $next($request);
    }

    /**
     * The setting is ON and the server has no token yet: the API stays closed
     * and says what opens it. A browser that asks for a page — the API docs —
     * gets the explanation as a page; every other client gets it as JSON.
     */
    protected function tokenMissing(Request $request): Response
    {
        if (in_array('text/html', $request->getAcceptableContentTypes(), true)) {
            return response()->view('larapilot::dashboard.protected', ['area' => 'api'], 503);
        }

        return response()->json([
            'message' => 'The Larapilot API is protected by a token (the api_auth project setting is ON) and no token has been set on this server yet. '
                .'Add LARAPILOT_API_TOKEN to the .env of this server, then send it as "Authorization: Bearer <token>" or in the X-Larapilot-Token header — '
                .'or lift the requirement with: php artisan larapilot:settings-set --api-auth=NO',
        ], 503);
    }
}
