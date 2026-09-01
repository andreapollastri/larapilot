<?php

declare(strict_types=1);

namespace Larapilot\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Larapilot\Services\ApiAuditService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes one audit line per mutating `/larapilot/api/*` request. Safe
 * (GET/HEAD/OPTIONS) requests are ignored by the service, so this is a
 * near-zero-cost pass-through for the read-heavy API.
 */
class AuditLarapilotApiWrites
{
    public function __construct(protected ApiAuditService $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $this->audit->record($request, $response);
        } catch (\Throwable) {
            // Auditing must never break an API response.
        }

        return $response;
    }
}
