<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\Auth\AuthAuditService;
use Larapilot\Services\ConfigService;
use Larapilot\Services\DashboardAuthService;
use Larapilot\Support\LarapilotCommand;

/**
 * The token of the Larapilot JSON API, from the shell. It is shown once,
 * when made: only its hash is kept, in `.larapilot/auth.yaml`.
 */
class ApiTokenCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:api-token
                            {action=show : show · generate · rotate · revoke}';

    protected $description = 'Show, generate, rotate, or revoke the token of the /larapilot/api JSON API (hashed in .larapilot/auth.yaml)';

    public function handle(DashboardAuthService $auth, ConfigService $config, AuthAuditService $audit): int
    {
        $action = strtolower(trim((string) $this->argument('action')));

        return match ($action) {
            'show', 'status' => $this->success('api_token', ['action' => 'show'] + $this->state($auth, $config)),
            'generate', 'rotate', 'create', 'new' => $this->generate($auth, $config, $audit, $action),
            'revoke', 'delete', 'remove' => $this->revoke($auth, $config, $audit),
            default => $this->failure(
                'E_INVALID_INPUT',
                "Unknown action: {$action}.",
                $this->exitForCode('E_INVALID_INPUT'),
                'Use one of: show, generate, rotate, revoke.'
            ),
        };
    }

    protected function generate(DashboardAuthService $auth, ConfigService $config, AuthAuditService $audit, string $action): int
    {
        $had = $auth->apiToken() !== null;
        $token = $auth->issueApiToken('cli');
        $audit->record($had ? 'token.rotated' : 'token.generated', ['actor' => 'cli']);

        return $this->success('api_token', [
            'action' => $had ? 'rotate' : 'generate',
            'token' => $token,
            'hint' => 'Copy the token now: it is not shown again, only its hash is kept. Send it as "Authorization: Bearer <token>" or in the X-Larapilot-Token header.'
                .($had ? ' The token there was no longer works.' : ''),
        ] + $this->state($auth, $config));
    }

    protected function revoke(DashboardAuthService $auth, ConfigService $config, AuthAuditService $audit): int
    {
        if (! $auth->revokeApiToken()) {
            return $this->failure('E_NOT_FOUND', 'There is no API token to revoke.', $this->exitForCode('E_NOT_FOUND'));
        }

        $audit->record('token.revoked', ['actor' => 'cli']);

        return $this->success('api_token', ['action' => 'revoke'] + $this->state($auth, $config));
    }

    /**
     * @return array<string, mixed>
     */
    protected function state(DashboardAuthService $auth, ConfigService $config): array
    {
        $token = $auth->apiToken();
        $env = (string) config('larapilot.api.token', '') !== '';

        return [
            'configured' => $token !== null || $env,
            'file_token' => $token,
            'env_token' => $env,
            'path' => $config->relativePath($auth->path()),
            'api_auth' => $config->apiAuthEnabled() ? 'YES' : 'NO',
        ];
    }
}
