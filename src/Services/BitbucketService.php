<?php

declare(strict_types=1);

namespace Larapilot\Services;

class BitbucketService
{
    public function __construct(
        protected ConfigService $config,
        protected GitService $git,
    ) {}

    /**
     * Probe Bitbucket Cloud readiness for the optional `settings.bitbucket` integration.
     *
     * Auth is via access token or username + app password (no required first-party CLI).
     *
     * @return array{
     *     enabled: bool,
     *     authenticated: bool,
     *     auth_method: string|null,
     *     repo: string|null,
     *     origin: string|null,
     *     is_bitbucket_remote: bool,
     *     ready: bool,
     *     hints: list<string>
     * }
     */
    public function status(): array
    {
        $enabled = $this->config->bitbucketEnabled();
        $authMethod = $this->authMethod();
        $authenticated = $authMethod !== null;
        $origin = $this->git->originUrl();
        $repo = $this->git->originRepoSlug();
        $isBitbucket = $this->git->originProvider() === 'bitbucket';

        $hints = [];

        if (! $enabled) {
            $hints[] = 'Enable with: php artisan larapilot:settings-set --bitbucket=YES';
        }

        if (! $authenticated) {
            $hints[] = 'Set BITBUCKET_ACCESS_TOKEN (or LARAPILOT_BITBUCKET_ACCESS_TOKEN), or BITBUCKET_USERNAME + BITBUCKET_APP_PASSWORD.';
        }

        if ($origin === null) {
            $hints[] = 'Add a git remote named origin pointing at a bitbucket.org repository.';
        } elseif (! $isBitbucket) {
            $hints[] = 'origin is not a bitbucket.org remote; Larapilot Bitbucket integration targets Bitbucket Cloud.';
        }

        $ready = $enabled && $authenticated && $isBitbucket;

        return [
            'enabled' => $enabled,
            'authenticated' => $authenticated,
            'auth_method' => $authMethod,
            'repo' => $repo,
            'origin' => $origin,
            'is_bitbucket_remote' => $isBitbucket,
            'ready' => $ready,
            'hints' => $hints,
        ];
    }

    protected function authMethod(): ?string
    {
        $token = trim((string) config('larapilot.integrations.bitbucket_access_token', ''));

        if ($token !== '') {
            return 'access_token';
        }

        $user = trim((string) config('larapilot.integrations.bitbucket_username', ''));
        $password = trim((string) config('larapilot.integrations.bitbucket_app_password', ''));

        if ($user !== '' && $password !== '') {
            return 'app_password';
        }

        return null;
    }
}
