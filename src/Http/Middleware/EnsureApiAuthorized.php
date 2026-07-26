<?php

declare(strict_types=1);

namespace Larapilot\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional token auth for the Larapilot JSON API.
 *
 * When `LARAPILOT_API_TOKEN` is set, every API request must carry it as a
 * bearer token or `X-Larapilot-Token` header. When no token is configured,
 * read requests stay open (dev/staging gate applies upstream) but mutating
 * requests are only allowed in local-style environments, because CSRF is
 * disabled on this route group.
 */
class EnsureApiAuthorized
{
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

        if (! $request->isMethodSafe() && ! app()->environment(['local', 'development', 'testing'])) {
            abort(403, 'Set LARAPILOT_API_TOKEN to allow Larapilot API writes outside local environments.');
        }

        return $next($request);
    }
}
