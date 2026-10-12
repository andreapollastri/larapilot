<?php

declare(strict_types=1);

namespace Larapilot\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Larapilot\Services\Auth\AccessPolicy;
use Larapilot\Services\Auth\AuthAuditService;
use Larapilot\Services\Auth\DashboardSession;
use Larapilot\Services\DashboardAuthService;
use Larapilot\Support\QrSvg;
use Larapilot\Support\Totp;

/**
 * The door of the dashboard: the sign-in, the code of the authenticator,
 * its enrolment where production asks one of everybody, and the way out.
 */
class AuthController
{
    public function __construct(
        protected DashboardSession $session,
        protected DashboardAuthService $users,
        protected AccessPolicy $policy,
        protected AuthAuditService $audit,
    ) {}

    public function showLogin(): View|RedirectResponse|Response
    {
        if ($this->policy->isOpen()) {
            return redirect()->route('larapilot.dashboard.index');
        }

        if (! $this->policy->requiresSignIn()) {
            abort(404);
        }

        if (! $this->users->hasUsers()) {
            return response()->view('larapilot::dashboard.protected', ['area' => 'dashboard'], 503);
        }

        if ($this->session->user() !== null) {
            return $this->session->needsTotpSetup()
                ? redirect()->route('larapilot.auth.two-factor.setup')
                : $this->afterSignIn();
        }

        $pending = $this->session->pendingUser();

        if ($pending !== null) {
            return redirect()->route($pending['totp'] === null ? 'larapilot.auth.two-factor.setup' : 'larapilot.auth.two-factor');
        }

        return view('larapilot::auth.login', [
            'mode' => $this->policy->mode(),
            'totp_mandatory' => $this->policy->requiresTotp(),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        if ($this->policy->isOpen()) {
            return redirect()->route('larapilot.dashboard.index');
        }

        if (! $this->policy->requiresSignIn()) {
            abort(404);
        }

        $username = mb_substr(trim((string) $request->input('username', '')), 0, 255);
        $password = (string) $request->input('password', '');

        if ($username === '' || $password === '') {
            return $this->loginFailed($username, 'Enter your username and your password.');
        }

        $locked = $this->session->lockedFor($username, $request);

        if ($locked > 0) {
            $this->audit->record('login.locked', ['user' => mb_substr($username, 0, 64)]);

            return $this->loginFailed($username, "Too many attempts. Try again in {$locked} seconds.");
        }

        return match ($this->session->attempt($username, $password, $request)) {
            DashboardSession::SIGNED_IN => $this->afterSignIn(),
            DashboardSession::NEEDS_TOTP => redirect()->route('larapilot.auth.two-factor'),
            DashboardSession::NEEDS_SETUP => redirect()->route('larapilot.auth.two-factor.setup'),
            default => $this->loginFailed($username, 'These credentials do not match.'),
        };
    }

    public function showTwoFactor(): View|RedirectResponse
    {
        if ($this->policy->isOpen()) {
            return redirect()->route('larapilot.dashboard.index');
        }

        $pending = $this->session->pendingUser();

        if ($pending === null) {
            return redirect()->route('larapilot.auth.login');
        }

        if ($pending['totp'] === null) {
            return redirect()->route('larapilot.auth.two-factor.setup');
        }

        return view('larapilot::auth.two-factor', ['username' => $pending['name']]);
    }

    public function twoFactor(Request $request): RedirectResponse
    {
        $pending = $this->session->pendingUser();

        if ($pending === null) {
            return redirect()->route('larapilot.auth.login');
        }

        $locked = $this->session->totpLockedFor($pending['name']);

        if ($locked > 0) {
            return redirect()->route('larapilot.auth.two-factor')->withErrors(['code' => "Too many codes. Try again in {$locked} seconds."]);
        }

        if ($this->session->verifyTotp((string) $request->input('code', ''))) {
            return $this->afterSignIn();
        }

        return redirect()->route('larapilot.auth.two-factor')->withErrors(['code' => 'That code is not right, or was used already. Wait for the next one and try again.']);
    }

    /**
     * Production lets nobody in without an authenticator: a user who has
     * none enrols one here, right after the password.
     */
    public function showSetup(): View|RedirectResponse
    {
        if ($this->policy->isOpen()) {
            return redirect()->route('larapilot.dashboard.index');
        }

        $who = $this->session->enrolling();

        if ($who === null) {
            return redirect()->route('larapilot.auth.login');
        }

        if ($who['totp'] !== null) {
            return $this->session->user() !== null ? $this->afterSignIn() : redirect()->route('larapilot.auth.two-factor');
        }

        if (! $this->policy->requiresTotp()) {
            // Optional here: the account page is where one enrols by choice.
            return $this->session->user() !== null ? redirect()->route('larapilot.dashboard.account.two-factor') : redirect()->route('larapilot.auth.login');
        }

        return view('larapilot::auth.two-factor-setup', self::setupData($who['name'], $this->session->beginTotpSetup()));
    }

    public function setup(Request $request): RedirectResponse
    {
        $who = $this->session->enrolling();

        if ($who === null) {
            return redirect()->route('larapilot.auth.login');
        }

        $locked = $this->session->totpLockedFor($who['name']);

        if ($locked > 0) {
            return redirect()->route('larapilot.auth.two-factor.setup')->withErrors(['code' => "Too many codes. Try again in {$locked} seconds."]);
        }

        if ($this->session->confirmTotpSetup((string) $request->input('code', ''))) {
            return $this->afterSignIn()->with('larapilot_success', 'Your authenticator is set up. From now on every sign-in asks for its code.');
        }

        return redirect()->route('larapilot.auth.two-factor.setup')->withErrors(['code' => 'That code does not match this secret. Scan the code again and enter the six digits the app shows now.']);
    }

    public function logout(): RedirectResponse
    {
        $this->session->signOut();

        if ($this->policy->isOpen()) {
            return redirect()->route('larapilot.dashboard.index');
        }

        return redirect()->route('larapilot.auth.login')->with('larapilot_success', 'You are signed out.');
    }

    /**
     * What an enrolment page shows: the QR code, the secret for whoever
     * types it, the name the app will show.
     *
     * @return array{username: string, secret: string, secret_pretty: string, qr: string, issuer: string}
     */
    public static function setupData(string $username, string $secret): array
    {
        $issuer = (string) (config('larapilot.dashboard_route.auth.issuer') ?: config('app.name') ?: 'Larapilot');

        return [
            'username' => $username,
            'secret' => $secret,
            'secret_pretty' => Totp::pretty($secret),
            'qr' => QrSvg::render(Totp::uri($issuer, $username, $secret)),
            'issuer' => $issuer,
        ];
    }

    protected function loginFailed(string $username, string $message): RedirectResponse
    {
        return redirect()->route('larapilot.auth.login')
            ->withErrors(['username' => $message])
            ->withInput(['username' => $username]);
    }

    protected function afterSignIn(): RedirectResponse
    {
        return redirect()->intended(route('larapilot.dashboard.index'));
    }
}
