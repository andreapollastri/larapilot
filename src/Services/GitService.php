<?php

declare(strict_types=1);

namespace Larapilot\Services;

class GitService
{
    public function __construct(
        protected ConfigService $config,
    ) {}

    public function isRepository(): bool
    {
        return $this->git('rev-parse', '--is-inside-work-tree') === 'true';
    }

    /**
     * @return array{sha: string, short_sha: string, subject: string, committed_at: string, url: string|null}|null
     */
    public function resolveTaskCommit(string $code, string $taskId, ?string $explicitSha = null): ?array
    {
        if (! $this->isRepository()) {
            return null;
        }

        if ($explicitSha !== null && trim($explicitSha) !== '') {
            return $this->commitDetails(trim($explicitSha));
        }

        $taskNeedle = strtoupper($taskId);
        $codeNeedle = strtoupper($code);
        $fallbackSha = null;

        $log = $this->git('log', '--format=%H%x1f%s', '-n', '100');

        if ($log === null || $log === '') {
            return null;
        }

        foreach (explode("\n", $log) as $line) {
            if ($line === '') {
                continue;
            }

            $parts = explode("\x1f", $line, 2);

            if (count($parts) < 2) {
                continue;
            }

            [$sha, $subject] = $parts;
            $haystack = strtoupper($subject);

            if (! str_contains($haystack, $taskNeedle)) {
                continue;
            }

            if (str_contains($haystack, $codeNeedle)) {
                return $this->commitDetails($sha);
            }

            $fallbackSha ??= $sha;
        }

        return $fallbackSha !== null ? $this->commitDetails($fallbackSha) : null;
    }

    /**
     * @return array{sha: string, short_sha: string, subject: string, committed_at: string, url: string|null}|null
     */
    public function resolveMergeCommit(string $code, ?string $explicitSha = null): ?array
    {
        if (! $this->isRepository()) {
            return null;
        }

        if ($explicitSha !== null && trim($explicitSha) !== '') {
            return $this->commitDetails(trim($explicitSha));
        }

        $codeNeedle = strtoupper($code);
        $branchNeedles = [
            'FEATURE/'.$codeNeedle,
            'FEATURE/'.strtolower($code),
        ];

        $merges = $this->git('log', '--merges', '--format=%H%x1f%s', '-n', '100');

        if ($merges !== null && $merges !== '') {
            foreach (explode("\n", $merges) as $line) {
                if ($line === '' || ! $this->subjectReferencesSpec($line, $codeNeedle, $branchNeedles)) {
                    continue;
                }

                $commit = $this->commitDetailsFromLogLine($line);

                if ($commit !== null) {
                    return $commit;
                }
            }
        }

        $log = $this->git('log', '--format=%H%x1f%s', '-n', '100');

        if ($log === null || $log === '') {
            return null;
        }

        $squashCandidate = null;

        foreach (explode("\n", $log) as $line) {
            if ($line === '' || ! $this->subjectReferencesSpec($line, $codeNeedle, $branchNeedles)) {
                continue;
            }

            $subject = $this->subjectFromLogLine($line);

            if ($subject !== null && $this->looksLikeMergeSubject($subject)) {
                return $this->commitDetailsFromLogLine($line);
            }

            if ($subject !== null && ! preg_match('/TASK-\d+/i', $subject)) {
                $squashCandidate ??= $line;
            }
        }

        return $squashCandidate !== null ? $this->commitDetailsFromLogLine($squashCandidate) : null;
    }

    /**
     * @param  list<string>  $branchNeedles
     */
    protected function subjectReferencesSpec(string $line, string $codeNeedle, array $branchNeedles): bool
    {
        $subject = $this->subjectFromLogLine($line);

        if ($subject === null) {
            return false;
        }

        $haystack = strtoupper($subject);

        if (str_contains($haystack, $codeNeedle)) {
            return true;
        }

        foreach ($branchNeedles as $needle) {
            if (str_contains($haystack, strtoupper($needle))) {
                return true;
            }
        }

        return false;
    }

    protected function looksLikeMergeSubject(string $subject): bool
    {
        $haystack = strtoupper($subject);

        return str_contains($haystack, 'MERGE PULL REQUEST')
            || str_contains($haystack, 'MERGE BRANCH')
            || str_contains($haystack, 'SEE MERGE REQUEST')
            || str_contains($haystack, 'MERGED IN');
    }

    /**
     * @return array{sha: string, short_sha: string, subject: string, committed_at: string, url: string|null}|null
     */
    protected function commitDetailsFromLogLine(string $line): ?array
    {
        $parts = explode("\x1f", $line, 2);

        if (count($parts) < 1 || $parts[0] === '') {
            return null;
        }

        return $this->commitDetails($parts[0]);
    }

    protected function subjectFromLogLine(string $line): ?string
    {
        $parts = explode("\x1f", $line, 2);

        return $parts[1] ?? null;
    }

    /**
     * @return array{sha: string, short_sha: string, subject: string, committed_at: string, url: string|null}|null
     */
    public function commitDetails(string $sha): ?array
    {
        if (! $this->isRepository()) {
            return null;
        }

        $resolved = $this->git('rev-parse', '--verify', $sha.'^{commit}');

        if ($resolved === null || $resolved === '') {
            return null;
        }

        $subject = $this->git('show', '-s', '--format=%s', $resolved) ?? '';
        $committedAt = $this->git('show', '-s', '--format=%aI', $resolved) ?? '';

        return [
            'sha' => $resolved,
            'short_sha' => substr($resolved, 0, 7),
            'subject' => $subject,
            'committed_at' => $committedAt,
            'url' => $this->commitUrl($resolved),
        ];
    }

    public function commitUrl(string $sha): ?string
    {
        $remote = $this->git('remote', 'get-url', 'origin');

        if ($remote === null || $remote === '') {
            return null;
        }

        if (preg_match('#^https?://([^/]+)/([^/]+)/([^/.]+)(?:\.git)?#i', $remote, $matches) === 1) {
            return $this->hostCommitUrl(strtolower($matches[1]), $matches[1], $matches[2], $matches[3], $sha);
        }

        if (preg_match('#^git@([^:]+):([^/]+)/([^/.]+)(?:\.git)?$#', $remote, $matches) === 1) {
            return $this->hostCommitUrl(strtolower($matches[1]), $matches[1], $matches[2], $matches[3], $sha);
        }

        return null;
    }

    protected function hostCommitUrl(string $host, string $displayHost, string $owner, string $repo, string $sha): ?string
    {
        if (str_contains($host, 'github.com')) {
            return "https://{$displayHost}/{$owner}/{$repo}/commit/{$sha}";
        }

        if (str_contains($host, 'gitlab.com')) {
            return "https://{$displayHost}/{$owner}/{$repo}/-/commit/{$sha}";
        }

        return null;
    }

    protected function git(string ...$args): ?string
    {
        $command = 'git -C '.escapeshellarg($this->config->projectRoot()).' ';

        foreach ($args as $arg) {
            $command .= escapeshellarg($arg).' ';
        }

        $command .= '2>/dev/null';
        $output = shell_exec($command);

        if (! is_string($output)) {
            return null;
        }

        return trim($output);
    }
}
