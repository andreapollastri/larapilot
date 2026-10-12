<?php

declare(strict_types=1);

namespace Larapilot\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Larapilot\Services\Auth\AccessPolicy;
use Larapilot\Services\Auth\DashboardSession;
use Larapilot\Services\DashboardAuthService;
use Symfony\Component\HttpFoundation\Response;

/**
 * The door of the `/larapilot` dashboard, of the mockups, and of their
 * assets. What it asks follows `APP_ENV` (see `AccessPolicy`):
 *
 *   - open (local, testing): nothing — whoever reaches it acts as an admin;
 *   - sign-in (any other environment): a user of `.larapilot/auth.yaml`,
 *     and their authenticator when they have one;
 *   - production, with `LARAPILOT_FORCE_IN_PROD=true`: a user and an
 *     authenticator, enrolled on the first sign-in if need be.
 *
 * Once in, the role of the user decides which pages answer: a page of
 * another role answers 403. With a sign-in asked and no user created yet
 * the dashboard fails closed (503) on a page that says how to create one.
 * The JSON API has its own door (`EnsureApiAuthorized`).
 */
class EnsureDashboardAuthorized
{
    public function __construct(
        protected DashboardSession $session,
        protected DashboardAuthService $users,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $policy = $this->session->policy();

        if ($policy->isOpen()) {
            return $next($request);
        }

        if (! $policy->requiresSignIn()) {
            abort(404);
        }

        if (! $this->users->hasUsers()) {
            return $this->firstUserMissing($request);
        }

        $user = $this->session->user();

        if ($user === null) {
            $pending = $this->session->pendingUser();

            if ($pending !== null) {
                return $pending['totp'] === null
                    ? $this->sendToDoor($request, 'larapilot.auth.two-factor.setup', 'Production asks every user for an authenticator: set yours up to go on.')
                    : $this->sendToDoor($request, 'larapilot.auth.two-factor', 'Enter the code of your authenticator to go on.');
            }

            return $this->sendToDoor($request, 'larapilot.auth.login', 'Sign in to open the Larapilot dashboard.');
        }

        if ($this->session->needsTotpSetup()) {
            return $this->sendToDoor($request, 'larapilot.auth.two-factor.setup', 'Production asks every user for an authenticator: set yours up to go on.');
        }

        $area = $policy->areaOfRoute($request->route()?->getName()) ?? '';

        if (! $policy->allows($user['role'], $area)) {
            return $this->forbidden($request, $user['role'], $area);
        }

        return $next($request);
    }

    /**
     * Nobody is signed in: a page goes to the sign-in — and comes back here
     * after it — and a client that asked for JSON is told so.
     */
    protected function sendToDoor(Request $request, string $route, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 401);
        }

        if ($request->isMethod('GET')) {
            return redirect()->guest(route($route));
        }

        return redirect()->route($route);
    }

    /**
     * A page the role of the user does not open.
     */
    protected function forbidden(Request $request, string $role, string $area): Response
    {
        $message = 'This page is not for the '.AccessPolicy::roleLabel($role).' role.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return response()->view('larapilot::dashboard.forbidden', [
            'role' => $role,
            'area' => $area,
        ], 403);
    }

    /**
     * A sign-in is asked and nobody can give one yet: the dashboard stays
     * closed and says what opens it, instead of failing with a bare error.
     */
    protected function firstUserMissing(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'The Larapilot dashboard asks for a sign-in in this environment (APP_ENV='.app()->environment().') and no user has been created yet. '
                    .'Create the first one with: php artisan larapilot:dashboard-user add <username> --role=admin',
            ], 503);
        }

        return response()->view('larapilot::dashboard.protected', ['area' => 'dashboard'], 503);
    }
}
