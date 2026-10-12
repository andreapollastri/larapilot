<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use InvalidArgumentException;
use Larapilot\Services\Auth\AccessPolicy;
use Larapilot\Services\Auth\AuthAuditService;
use Larapilot\Services\ConfigService;
use Larapilot\Services\DashboardAuthService;
use Larapilot\Support\LarapilotCommand;

/**
 * The users of the `/larapilot` dashboard, from the shell: on a server
 * with no admin yet — a fresh deploy, production — this is how the first
 * one is made. Everything it does, the Users page does too.
 */
class DashboardUserCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:dashboard-user
                            {action=list : list · add · remove · role · password · rename · totp-reset}
                            {username? : The user the action is about}
                            {value? : The role for `role` (admin, dev, pm, ext), the new name for `rename`}
                            {--password= : Password for add / password (prompted securely when omitted in an interactive shell)}
                            {--role=admin : Role for add — admin (default), dev, pm, or ext}';

    protected $description = 'Manage the users and roles of the /larapilot dashboard (hashed in .larapilot/auth.yaml)';

    public function handle(DashboardAuthService $auth, ConfigService $config, AccessPolicy $policy, AuthAuditService $audit): int
    {
        $action = strtolower(trim((string) $this->argument('action')));

        try {
            return match ($action) {
                'list', 'ls' => $this->list($auth, $config, $policy),
                'add', 'create' => $this->add($auth, $config, $policy, $audit),
                'remove', 'delete', 'rm' => $this->remove($auth, $config, $policy, $audit),
                'role' => $this->role($auth, $config, $policy, $audit),
                'password', 'passwd' => $this->password($auth, $config, $policy, $audit),
                'rename' => $this->rename($auth, $config, $policy, $audit),
                'totp-reset', '2fa-reset' => $this->totpReset($auth, $config, $policy, $audit),
                default => $this->failure(
                    'E_INVALID_INPUT',
                    "Unknown action: {$action}.",
                    $this->exitForCode('E_INVALID_INPUT'),
                    'Use one of: list, add, remove, role, password, rename, totp-reset.'
                ),
            };
        } catch (InvalidArgumentException $exception) {
            return $this->failure('E_INVALID_INPUT', $exception->getMessage(), $this->exitForCode('E_INVALID_INPUT'));
        }
    }

    protected function list(DashboardAuthService $auth, ConfigService $config, AccessPolicy $policy): int
    {
        return $this->success('dashboard_user', ['action' => 'list'] + $this->state($auth, $config, $policy));
    }

    protected function add(DashboardAuthService $auth, ConfigService $config, AccessPolicy $policy, AuthAuditService $audit): int
    {
        $username = $this->requireUsername('add');

        if ($username === null) {
            return $this->exitForCode('E_INVALID_INPUT');
        }

        if ($auth->user($username) !== null) {
            return $this->failure(
                'E_PRECONDITION',
                "There is already a user named {$username}.",
                $this->exitForCode('E_PRECONDITION'),
                "Set a new password with: php artisan larapilot:dashboard-user password {$username}"
            );
        }

        $role = DashboardAuthService::assertRole((string) ($this->option('role') ?? AccessPolicy::ROLE_ADMIN));
        $password = $this->askPassword();

        if ($password === null) {
            return $this->exitForCode('E_INVALID_INPUT');
        }

        $auth->createUser($username, $password, $role);
        $audit->record('user.created', ['user' => $username, 'role' => $role, 'actor' => 'cli']);

        return $this->success('dashboard_user', ['action' => 'add', 'username' => $username, 'role' => $role] + $this->state($auth, $config, $policy));
    }

    protected function remove(DashboardAuthService $auth, ConfigService $config, AccessPolicy $policy, AuthAuditService $audit): int
    {
        $username = $this->requireUsername('remove');

        if ($username === null) {
            return $this->exitForCode('E_INVALID_INPUT');
        }

        if (! $auth->removeUser($username)) {
            return $this->failure('E_NOT_FOUND', "No dashboard user named {$username}.", $this->exitForCode('E_NOT_FOUND'));
        }

        $audit->record('user.removed', ['user' => $username, 'actor' => 'cli']);

        $state = $this->state($auth, $config, $policy);

        if ($state['count'] === 0 && $policy->requiresSignIn()) {
            $state['hint'] = 'No user is left: the dashboard stays closed (HTTP 503) until you add one.';
        } elseif ($state['admins'] === []) {
            $state['hint'] = 'No admin is left: nobody can manage the users or the API token from the dashboard until one is made here.';
        }

        return $this->success('dashboard_user', ['action' => 'remove', 'username' => $username] + $state);
    }

    protected function role(DashboardAuthService $auth, ConfigService $config, AccessPolicy $policy, AuthAuditService $audit): int
    {
        $username = $this->requireUsername('role <username> <role>');

        if ($username === null) {
            return $this->exitForCode('E_INVALID_INPUT');
        }

        $role = trim((string) $this->argument('value'));

        if ($role === '') {
            return $this->failure(
                'E_INVALID_INPUT',
                'A role is required: php artisan larapilot:dashboard-user role <username> <admin|dev|pm|ext>.',
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        if ($auth->user($username) === null) {
            return $this->failure('E_NOT_FOUND', "No dashboard user named {$username}.", $this->exitForCode('E_NOT_FOUND'));
        }

        $role = DashboardAuthService::assertRole($role);
        $auth->setRole($username, $role);
        $audit->record('user.role', ['user' => $username, 'role' => $role, 'actor' => 'cli']);

        return $this->success('dashboard_user', ['action' => 'role', 'username' => $username, 'role' => $role] + $this->state($auth, $config, $policy));
    }

    protected function password(DashboardAuthService $auth, ConfigService $config, AccessPolicy $policy, AuthAuditService $audit): int
    {
        $username = $this->requireUsername('password');

        if ($username === null) {
            return $this->exitForCode('E_INVALID_INPUT');
        }

        if ($auth->user($username) === null) {
            return $this->failure('E_NOT_FOUND', "No dashboard user named {$username}.", $this->exitForCode('E_NOT_FOUND'));
        }

        $password = $this->askPassword();

        if ($password === null) {
            return $this->exitForCode('E_INVALID_INPUT');
        }

        $auth->setPassword($username, $password);
        $audit->record('user.password', ['user' => $username, 'actor' => 'cli']);

        return $this->success('dashboard_user', ['action' => 'password', 'username' => $username, 'hint' => 'Every open session of this user is signed out.'] + $this->state($auth, $config, $policy));
    }

    protected function rename(DashboardAuthService $auth, ConfigService $config, AccessPolicy $policy, AuthAuditService $audit): int
    {
        $username = $this->requireUsername('rename <username> <new-name>');

        if ($username === null) {
            return $this->exitForCode('E_INVALID_INPUT');
        }

        $newName = trim((string) $this->argument('value'));

        if ($newName === '') {
            return $this->failure(
                'E_INVALID_INPUT',
                'A new name is required: php artisan larapilot:dashboard-user rename <username> <new-name>.',
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        if ($auth->user($username) === null) {
            return $this->failure('E_NOT_FOUND', "No dashboard user named {$username}.", $this->exitForCode('E_NOT_FOUND'));
        }

        $auth->rename($username, $newName);
        $audit->record('user.renamed', ['user' => $username, 'to' => $newName, 'actor' => 'cli']);

        return $this->success('dashboard_user', ['action' => 'rename', 'username' => $newName, 'from' => $username] + $this->state($auth, $config, $policy));
    }

    protected function totpReset(DashboardAuthService $auth, ConfigService $config, AccessPolicy $policy, AuthAuditService $audit): int
    {
        $username = $this->requireUsername('totp-reset');

        if ($username === null) {
            return $this->exitForCode('E_INVALID_INPUT');
        }

        if ($auth->user($username) === null) {
            return $this->failure('E_NOT_FOUND', "No dashboard user named {$username}.", $this->exitForCode('E_NOT_FOUND'));
        }

        $had = $auth->disableTotp($username);
        $audit->record('totp.reset', ['user' => $username, 'actor' => 'cli']);

        return $this->success('dashboard_user', [
            'action' => 'totp-reset',
            'username' => $username,
            'had_totp' => $had,
            'hint' => $had
                ? ($policy->requiresTotp() ? 'The user enrols a new authenticator at the next sign-in.' : 'The user signs in with the password alone until they enrol a new authenticator from their account.')
                : 'This user had no authenticator.',
        ] + $this->state($auth, $config, $policy));
    }

    protected function requireUsername(string $usage): ?string
    {
        $username = trim((string) $this->argument('username'));

        if ($username === '') {
            $this->failure(
                'E_INVALID_INPUT',
                "A username is required: php artisan larapilot:dashboard-user {$usage}.",
                $this->exitForCode('E_INVALID_INPUT')
            );

            return null;
        }

        return $username;
    }

    /**
     * The password from `--password`, or asked twice in an interactive shell.
     */
    protected function askPassword(): ?string
    {
        $password = (string) ($this->option('password') ?? '');

        if ($password === '' && $this->input->isInteractive()) {
            $password = (string) $this->secret('Password (at least '.DashboardAuthService::PASSWORD_MIN.' characters)');
            $confirm = (string) $this->secret('Confirm password');

            if ($password !== $confirm) {
                $this->failure('E_INVALID_INPUT', 'The passwords did not match.', $this->exitForCode('E_INVALID_INPUT'));

                return null;
            }
        }

        if ($password === '') {
            $this->failure(
                'E_INVALID_INPUT',
                'A password is required. Pass --password=… or run the command in an interactive shell.',
                $this->exitForCode('E_INVALID_INPUT')
            );

            return null;
        }

        return $password;
    }

    /**
     * @return array{users: list<array{username: string, role: string, role_label: string, totp: bool, last_login_at: string|null}>, count: int, admins: list<string>, mode: string, path: string, hint: string|null}
     */
    protected function state(DashboardAuthService $auth, ConfigService $config, AccessPolicy $policy): array
    {
        $users = [];

        foreach ($auth->users() as $name => $user) {
            $users[] = [
                'username' => $name,
                'role' => $user['role'],
                'role_label' => AccessPolicy::roleLabel($user['role']),
                'totp' => $user['totp'] !== null,
                'last_login_at' => $user['last_login_at'],
            ];
        }

        $hint = null;

        if ($users === [] && $policy->requiresSignIn()) {
            $hint = 'The dashboard asks for a sign-in in this environment and has no user: it stays closed (HTTP 503) until you add one — php artisan larapilot:dashboard-user add <username> --role=admin';
        } elseif ($policy->isOpen()) {
            $hint = 'APP_ENV is '.app()->environment().': the dashboard is open here, no sign-in is asked. The users count on every other environment.';
        }

        return [
            'users' => $users,
            'count' => count($users),
            'admins' => $auth->admins(),
            'mode' => $policy->mode(),
            'path' => $config->relativePath($auth->path()),
            'hint' => $hint,
        ];
    }
}
