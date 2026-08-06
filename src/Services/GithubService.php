<?php

declare(strict_types=1);

namespace Larapilot\Services;

class GithubService
{
    public function __construct(
        protected ConfigService $config,
        protected GitService $git,
    ) {}

    /**
     * Probe GitHub CLI readiness for the optional `settings.github` integration.
     *
     * @return array{
     *     enabled: bool,
     *     gh_installed: bool,
     *     authenticated: bool,
     *     repo: string|null,
     *     origin: string|null,
     *     is_github_remote: bool,
     *     ready: bool,
     *     hints: list<string>
     * }
     */
    public function status(): array
    {
        $enabled = $this->config->githubEnabled();
        $ghInstalled = $this->ghInstalled();
        $authenticated = $ghInstalled && $this->ghAuthenticated();
        $origin = $this->git->originUrl();
        $repo = $this->git->originRepoSlug();
        $isGithub = $this->git->originProvider() === 'github';

        $hints = [];

        if (! $enabled) {
            $hints[] = 'Enable with: php artisan larapilot:settings-set --github=YES';
        }

        if (! $ghInstalled) {
            $hints[] = 'Install GitHub CLI: https://cli.github.com/ then run `gh auth login`.';
        } elseif (! $authenticated) {
            $hints[] = 'Authenticate: `gh auth login` (or set GH_TOKEN in the environment).';
        }

        if ($origin === null) {
            $hints[] = 'Add a git remote named origin pointing at a GitHub repository.';
        } elseif (! $isGithub) {
            $hints[] = 'origin is not a github.com remote; Larapilot GitHub integration targets GitHub only.';
        }

        $ready = $enabled && $ghInstalled && $authenticated && $isGithub;

        return [
            'enabled' => $enabled,
            'gh_installed' => $ghInstalled,
            'authenticated' => $authenticated,
            'repo' => $repo,
            'origin' => $origin,
            'is_github_remote' => $isGithub,
            'ready' => $ready,
            'hints' => $hints,
        ];
    }

    protected function ghInstalled(): bool
    {
        $output = shell_exec('command -v gh 2>/dev/null');

        return is_string($output) && trim($output) !== '';
    }

    protected function ghAuthenticated(): bool
    {
        $token = getenv('GH_TOKEN');

        if (is_string($token) && $token !== '') {
            return true;
        }

        $output = shell_exec('gh auth status 2>&1');

        if (! is_string($output) || $output === '') {
            return false;
        }

        return str_contains(strtolower($output), 'logged in to');
    }
}
