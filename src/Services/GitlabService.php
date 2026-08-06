<?php

declare(strict_types=1);

namespace Larapilot\Services;

class GitlabService
{
    public function __construct(
        protected ConfigService $config,
        protected GitService $git,
    ) {}

    /**
     * Probe GitLab CLI readiness for the optional `settings.gitlab` integration.
     *
     * @return array{
     *     enabled: bool,
     *     glab_installed: bool,
     *     authenticated: bool,
     *     repo: string|null,
     *     origin: string|null,
     *     is_gitlab_remote: bool,
     *     ready: bool,
     *     hints: list<string>
     * }
     */
    public function status(): array
    {
        $enabled = $this->config->gitlabEnabled();
        $glabInstalled = $this->glabInstalled();
        $authenticated = $glabInstalled && $this->glabAuthenticated();
        $origin = $this->git->originUrl();
        $repo = $this->git->originRepoSlug();
        $isGitlab = $this->git->originProvider() === 'gitlab';

        $hints = [];

        if (! $enabled) {
            $hints[] = 'Enable with: php artisan larapilot:settings-set --gitlab=YES';
        }

        if (! $glabInstalled) {
            $hints[] = 'Install GitLab CLI (glab): https://gitlab.com/gitlab-org/cli then run `glab auth login`.';
        } elseif (! $authenticated) {
            $hints[] = 'Authenticate: `glab auth login` (or set GITLAB_TOKEN / GLAB_TOKEN).';
        }

        if ($origin === null) {
            $hints[] = 'Add a git remote named origin pointing at a GitLab repository.';
        } elseif (! $isGitlab) {
            $hints[] = 'origin is not a GitLab remote; Larapilot GitLab integration targets GitLab hosts.';
        }

        $ready = $enabled && $glabInstalled && $authenticated && $isGitlab;

        return [
            'enabled' => $enabled,
            'glab_installed' => $glabInstalled,
            'authenticated' => $authenticated,
            'repo' => $repo,
            'origin' => $origin,
            'is_gitlab_remote' => $isGitlab,
            'ready' => $ready,
            'hints' => $hints,
        ];
    }

    protected function glabInstalled(): bool
    {
        $output = shell_exec('command -v glab 2>/dev/null');

        return is_string($output) && trim($output) !== '';
    }

    protected function glabAuthenticated(): bool
    {
        $token = getenv('GITLAB_TOKEN') ?: getenv('GLAB_TOKEN');

        if (is_string($token) && $token !== '') {
            return true;
        }

        $output = shell_exec('glab auth status 2>&1');

        if (! is_string($output) || $output === '') {
            return false;
        }

        $haystack = strtolower($output);

        return str_contains($haystack, 'logged in')
            || str_contains($haystack, 'authenticated');
    }
}
