<?php

declare(strict_types=1);

namespace Larapilot\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers for the dev/staging `/larapilot` dashboard and
 * JSON API. These surfaces are never served in production, but they still
 * benefit from conservative defaults on shared staging hosts: no MIME
 * sniffing and no referrer leakage everywhere, plus no framing of the
 * dashboard UI.
 *
 * Wire it with the route-parameter syntax:
 *   AddLarapilotSecurityHeaders::class.':api'     (JSON API group — no frame header)
 *   AddLarapilotSecurityHeaders::class.':embed'   (framed by the dashboard itself)
 *   AddLarapilotSecurityHeaders::class            (dashboard, default)
 */
class AddLarapilotSecurityHeaders
{
    public function handle(Request $request, Closure $next, string $surface = 'dashboard'): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff', false);
        $response->headers->set('Referrer-Policy', 'no-referrer', false);

        // The design viewer renders the presentation index inside an iframe on
        // the dashboard itself, so that one surface has to allow same-origin
        // framing — DENY makes the browser refuse to load it.
        if ($surface === 'embed') {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN', false);
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'", false);

            return $response;
        }

        if ($surface !== 'api') {
            $response->headers->set('X-Frame-Options', 'DENY', false);
        }

        return $response;
    }
}
