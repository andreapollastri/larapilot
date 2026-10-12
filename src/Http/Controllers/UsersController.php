<?php

declare(strict_types=1);

namespace Larapilot\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Larapilot\Services\Auth\AccessPolicy;
use Larapilot\Services\Auth\AuthAuditService;
use Larapilot\Services\Auth\DashboardSession;
use Larapilot\Services\ConfigService;
use Larapilot\Services\DashboardAuthService;

/**
 * The users of the dashboard, for admins: who they are, their role, a new
 * password, a new name, their authenticator reset, their removal. The
 * admin at the keyboard cannot remove themself, rename themself, or
 * change their own role — that is what the account page and the other
 * admins are for — and can do all of it to any other admin.
 */
class UsersController
{
    public function __construct(
        protected ConfigService $config,
        protected DashboardSession $session,
        protected DashboardAuthService $users,
        protected AccessPolicy $policy,
        protected AuthAuditService $audit,
    ) {}

    public function index(): View
    {
        $this->guard();

        $self = $this->session->name();
        $users = [];

        foreach ($this->users->users() as $name => $user) {
            $users[] = [
                'name' => $name,
                'role' => $user['role'],
                'role_label' => AccessPolicy::roleLabel($user['role']),
                'totp' => $user['totp'] !== null,
                'created_at' => $user['created_at'],
                'last_login_at' => $user['last_login_at'],
                'self' => $name === $self,
            ];
        }

        $roles = [];

        foreach (AccessPolicy::ROLES as $role) {
            $roles[$role] = [
                'label' => AccessPolicy::roleLabel($role),
                'description' => AccessPolicy::roleDescription($role),
                'areas' => array_map(static fn (string $area): string => AccessPolicy::areas()[$area]['label'], $this->policy->areasFor($role)),
            ];
        }

        return view('larapilot::dashboard.users', [
            'users' => $users,
            'roles' => $roles,
            'self' => $self,
            'mode' => $this->policy->mode(),
            'mode_label' => $this->policy->modeLabel(),
            'totp_mandatory' => $this->policy->requiresTotp(),
            'password_min' => DashboardAuthService::PASSWORD_MIN,
            'path' => $this->config->relativePath($this->users->path()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->guard();

        $username = trim((string) $request->input('username', ''));
        $password = (string) $request->input('password', '');
        $role = (string) $request->input('role', AccessPolicy::ROLE_EXT);

        if ($password !== (string) $request->input('password_confirmation', '')) {
            return $this->failed('The user was not created.', 'The password and its confirmation do not match.');
        }

        try {
            $this->users->createUser($username, $password, $role);
        } catch (InvalidArgumentException $exception) {
            return $this->failed('The user was not created.', $exception->getMessage());
        }

        $this->audit->record('user.created', ['user' => $username, 'role' => DashboardAuthService::assertRole($role), 'actor' => $this->actor()]);

        return $this->done("{$username} is created as ".AccessPolicy::roleLabel(DashboardAuthService::assertRole($role)).'.');
    }

    public function role(Request $request, string $user): RedirectResponse
    {
        $this->guard();

        if ($this->users->user($user) === null) {
            abort(404);
        }

        if ($this->isSelf($user)) {
            return $this->failed('Your role stays as it is.', 'You cannot change your own role: another admin can.');
        }

        try {
            $role = DashboardAuthService::assertRole((string) $request->input('role', ''));
            $this->users->setRole($user, $role);
        } catch (InvalidArgumentException $exception) {
            return $this->failed('The role was not changed.', $exception->getMessage());
        }

        $this->audit->record('user.role', ['user' => $user, 'role' => $role, 'actor' => $this->actor()]);

        return $this->done("{$user} is now ".AccessPolicy::roleLabel($role).'.');
    }

    public function password(Request $request, string $user): RedirectResponse
    {
        $this->guard();

        if ($this->users->user($user) === null) {
            abort(404);
        }

        $password = (string) $request->input('password', '');

        if ($password !== (string) $request->input('password_confirmation', '')) {
            return $this->failed('The password was not changed.', 'The password and its confirmation do not match.');
        }

        try {
            $this->users->setPassword($user, $password);
        } catch (InvalidArgumentException $exception) {
            return $this->failed('The password was not changed.', $exception->getMessage());
        }

        $this->audit->record('user.password', ['user' => $user, 'actor' => $this->actor()]);

        return $this->done("The password of {$user} is changed. Every open session of theirs is signed out.");
    }

    public function rename(Request $request, string $user): RedirectResponse
    {
        $this->guard();

        if ($this->users->user($user) === null) {
            abort(404);
        }

        if ($this->isSelf($user)) {
            return $this->failed('Your name stays as it is.', 'You cannot rename yourself: another admin can.');
        }

        $newName = trim((string) $request->input('name', ''));

        try {
            $this->users->rename($user, $newName);
        } catch (InvalidArgumentException $exception) {
            return $this->failed('The user was not renamed.', $exception->getMessage());
        }

        $this->audit->record('user.renamed', ['user' => $user, 'to' => $newName, 'actor' => $this->actor()]);

        return $this->done("{$user} is now {$newName}.");
    }

    public function resetTwoFactor(string $user): RedirectResponse
    {
        $this->guard();

        if ($this->users->user($user) === null) {
            abort(404);
        }

        if ($this->isSelf($user)) {
            return $this->failed('Your authenticator stays as it is.', 'Turn your own authenticator off from your account; in production, another admin can reset it.');
        }

        $had = $this->users->disableTotp($user);
        $this->audit->record('totp.reset', ['user' => $user, 'actor' => $this->actor()]);

        if (! $had) {
            return $this->done("{$user} had no authenticator.");
        }

        return $this->done("The authenticator of {$user} is reset. ".($this->policy->requiresTotp()
            ? 'They enrol a new one at their next sign-in.'
            : 'They sign in with the password alone until they enrol a new one.'));
    }

    public function destroy(string $user): RedirectResponse
    {
        $this->guard();

        if ($this->users->user($user) === null) {
            abort(404);
        }

        if ($this->isSelf($user)) {
            return $this->failed('You are still here.', 'You cannot remove yourself: another admin can.');
        }

        $this->users->removeUser($user);
        $this->audit->record('user.removed', ['user' => $user, 'actor' => $this->actor()]);

        return $this->done("{$user} is removed. Their open sessions are signed out.");
    }

    protected function isSelf(string $user): bool
    {
        $self = $this->session->name();

        return $self !== null && $self === $user;
    }

    protected function actor(): string
    {
        return $this->session->name() ?? 'dashboard';
    }

    protected function done(string $message): RedirectResponse
    {
        return redirect()->route('larapilot.dashboard.users')->with('larapilot_success', $message);
    }

    protected function failed(string $message, string $detail): RedirectResponse
    {
        return redirect()->route('larapilot.dashboard.users')
            ->with('larapilot_error', $message)
            ->with('larapilot_details', [$detail])
            ->withInput();
    }

    protected function guard(): void
    {
        if (! $this->config->dashboardBrowsable()) {
            abort(404);
        }
    }
}
