<?php

declare(strict_types=1);

namespace Larapilot\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Larapilot\Services\Auth\AccessPolicy;
use Larapilot\Services\Auth\DashboardSession;
use Larapilot\Services\ConfigService;
use Larapilot\Services\DashboardAuthService;

/**
 * One's own account: the password, and the authenticator — enrolled by
 * choice outside production, where it is mandatory and cannot be turned
 * off. Behind the dashboard gate, for every role.
 */
class AccountController
{
    public function __construct(
        protected ConfigService $config,
        protected DashboardSession $session,
        protected DashboardAuthService $users,
        protected AccessPolicy $policy,
    ) {}

    public function show(): View|RedirectResponse
    {
        $this->guard();

        $user = $this->session->user();

        if ($user === null) {
            return $this->open();
        }

        return view('larapilot::dashboard.account', [
            'account' => [
                'name' => $user['name'],
                'role' => $user['role'],
                'role_label' => AccessPolicy::roleLabel($user['role']),
                'role_description' => AccessPolicy::roleDescription($user['role']),
                'totp' => $user['totp'] !== null,
                'totp_since' => $user['totp']['confirmed_at'] ?? null,
                'created_at' => $user['created_at'],
                'last_login_at' => $user['last_login_at'],
            ],
            'totp_mandatory' => $this->policy->requiresTotp(),
            'password_min' => DashboardAuthService::PASSWORD_MIN,
        ]);
    }

    public function password(Request $request): RedirectResponse
    {
        $this->guard();

        if ($this->session->user() === null) {
            return $this->open();
        }

        $new = (string) $request->input('password', '');

        if ($new !== (string) $request->input('password_confirmation', '')) {
            return redirect()->route('larapilot.dashboard.account')->with('larapilot_error', 'The new password and its confirmation do not match.');
        }

        try {
            $this->session->changePassword((string) $request->input('current_password', ''), $new);
        } catch (InvalidArgumentException $exception) {
            return redirect()->route('larapilot.dashboard.account')->with('larapilot_error', $exception->getMessage());
        }

        return redirect()->route('larapilot.dashboard.account')->with('larapilot_success', 'Your password is changed. Every other session of yours is signed out.');
    }

    /**
     * The enrolment of an authenticator, by choice: the QR code and the
     * code that confirms it.
     */
    public function twoFactor(): View|RedirectResponse
    {
        $this->guard();

        $user = $this->session->user();

        if ($user === null) {
            return $this->open();
        }

        if ($user['totp'] !== null) {
            return redirect()->route('larapilot.dashboard.account')->with('larapilot_success', 'Your authenticator is already set up.');
        }

        return view('larapilot::dashboard.account-two-factor', AuthController::setupData($user['name'], $this->session->beginTotpSetup()));
    }

    public function enableTwoFactor(Request $request): RedirectResponse
    {
        $this->guard();

        $user = $this->session->user();

        if ($user === null) {
            return $this->open();
        }

        $locked = $this->session->totpLockedFor($user['name']);

        if ($locked > 0) {
            return redirect()->route('larapilot.dashboard.account.two-factor')->withErrors(['code' => "Too many codes. Try again in {$locked} seconds."]);
        }

        if ($this->session->confirmTotpSetup((string) $request->input('code', ''))) {
            return redirect()->route('larapilot.dashboard.account')->with('larapilot_success', 'Your authenticator is set up. From now on every sign-in asks for its code.');
        }

        return redirect()->route('larapilot.dashboard.account.two-factor')->withErrors(['code' => 'That code does not match this secret. Scan the code again and enter the six digits the app shows now.']);
    }

    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $this->guard();

        $user = $this->session->user();

        if ($user === null) {
            return $this->open();
        }

        if ($this->policy->requiresTotp()) {
            return redirect()->route('larapilot.dashboard.account')->with('larapilot_error', 'Production asks every user for an authenticator: it cannot be turned off here. An admin can reset it from the Users page.');
        }

        if (! $this->session->disableTotp((string) $request->input('password', ''), (string) $request->input('code', ''))) {
            return redirect()->route('larapilot.dashboard.account')->with('larapilot_error', 'The authenticator stays on: the password or the code is not right.');
        }

        return redirect()->route('larapilot.dashboard.account')->with('larapilot_success', 'Your authenticator is off. You sign in with the password alone.');
    }

    /**
     * On a developer's machine the dashboard is open: there is nobody to
     * have an account.
     */
    protected function open(): RedirectResponse
    {
        return redirect()->route('larapilot.dashboard.index')
            ->with('larapilot_error', 'The dashboard is open on this machine (APP_ENV='.app()->environment().'): there is no account to manage here. Users count on every other environment — see Users.');
    }

    protected function guard(): void
    {
        if (! $this->config->dashboardBrowsable()) {
            abort(404);
        }
    }
}
