<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\DashboardAuthService;
use Larapilot\Support\LarapilotCommand;

class DashboardUserCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:dashboard-user
                            {action=list : list, add, or remove}
                            {username? : Username for add / remove}
                            {--password= : Password for add (prompted securely when omitted in an interactive shell)}';

    protected $description = 'Manage HTTP Basic Auth users for the /larapilot dashboard (credentials hashed in .larapilot/auth.yaml)';

    public function handle(DashboardAuthService $auth, ConfigService $config): int
    {
        $action = strtolower(trim((string) $this->argument('action')));

        return match ($action) {
            'list' => $this->list($auth, $config),
            'add' => $this->add($auth, $config),
            'remove', 'delete', 'rm' => $this->remove($auth, $config),
            default => $this->failure(
                'E_INVALID_INPUT',
                "Unknown action: {$action}.",
                $this->exitForCode('E_INVALID_INPUT'),
                'Use one of: list, add, remove.'
            ),
        };
    }

    protected function list(DashboardAuthService $auth, ConfigService $config): int
    {
        $users = $auth->usernames();

        return $this->success('dashboard_user', [
            'action' => 'list',
            'users' => $users,
            'count' => count($users),
            'auth_enabled' => $config->dashboardAuthEnabled(),
            'path' => $config->relativePath($auth->path()),
            'hint' => $this->gateHint($config, $users),
        ]);
    }

    protected function add(DashboardAuthService $auth, ConfigService $config): int
    {
        $username = trim((string) $this->argument('username'));

        if ($username === '') {
            return $this->failure(
                'E_INVALID_INPUT',
                'A username is required: php artisan larapilot:dashboard-user add <username>.',
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        $password = (string) ($this->option('password') ?? '');

        if ($password === '' && $this->input->isInteractive()) {
            $password = (string) $this->secret('Password');
            $confirm = (string) $this->secret('Confirm password');

            if ($password !== $confirm) {
                return $this->failure(
                    'E_INVALID_INPUT',
                    'The passwords did not match.',
                    $this->exitForCode('E_INVALID_INPUT')
                );
            }
        }

        if ($password === '') {
            return $this->failure(
                'E_INVALID_INPUT',
                'A password is required. Pass --password=… or run the command in an interactive shell.',
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        $auth->setUser($username, $password);

        $users = $auth->usernames();

        return $this->success('dashboard_user', [
            'action' => 'add',
            'username' => $username,
            'users' => $users,
            'count' => count($users),
            'auth_enabled' => $config->dashboardAuthEnabled(),
            'path' => $config->relativePath($auth->path()),
            'hint' => $this->gateHint($config, $users),
        ]);
    }

    protected function remove(DashboardAuthService $auth, ConfigService $config): int
    {
        $username = trim((string) $this->argument('username'));

        if ($username === '') {
            return $this->failure(
                'E_INVALID_INPUT',
                'A username is required: php artisan larapilot:dashboard-user remove <username>.',
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        if (! $auth->removeUser($username)) {
            return $this->failure(
                'E_NOT_FOUND',
                "No dashboard user named {$username}.",
                $this->exitForCode('E_NOT_FOUND')
            );
        }

        $users = $auth->usernames();

        return $this->success('dashboard_user', [
            'action' => 'remove',
            'username' => $username,
            'users' => $users,
            'count' => count($users),
            'auth_enabled' => $config->dashboardAuthEnabled(),
            'path' => $config->relativePath($auth->path()),
            'hint' => $users === [] && $config->dashboardAuthEnabled()
                ? 'No users left while dashboard_auth is ON — the dashboard will return HTTP 500 until you add one or run: php artisan larapilot:settings-set --dashboard-auth=NO'
                : null,
        ]);
    }

    /**
     * @param  list<string>  $users
     */
    protected function gateHint(ConfigService $config, array $users): ?string
    {
        if ($users !== [] && ! $config->dashboardAuthEnabled()) {
            return 'Users are stored but the gate is OFF. Enable it with: php artisan larapilot:settings-set --dashboard-auth=YES';
        }

        if ($users === [] && $config->dashboardAuthEnabled()) {
            return 'dashboard_auth is ON but no users exist — add one with: php artisan larapilot:dashboard-user add <username>';
        }

        return null;
    }
}
