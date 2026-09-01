<?php

declare(strict_types=1);

namespace Larapilot\Services;

class GitService
{
    protected ?bool $isRepository = null;

    public function __construct(
        protected ConfigService $config,
    ) {}

    public function isRepository(): bool
    {
        // Cache only the positive answer: a repository can appear mid-process
        // (e.g. right after project bootstrap) but never stops being one.
        if ($this->isRepository === true) {
            return true;
        }

        return $this->isRepository = $this->git('rev-parse', '--is-inside-work-tree') === 'true';
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

            // Only fall back to a code-less subject: a subject that names a
            // different spec code belongs to another story's task.
            if (! $this->referencesAnotherSpec($haystack, $codeNeedle)) {
                $fallbackSha ??= $sha;
            }
        }

        return $fallbackSha !== null ? $this->commitDetails($fallbackSha) : null;
    }

    protected function referencesAnotherSpec(string $upperSubject, string $codeNeedle): bool
    {
        if (preg_match_all('/\b(?!TASK-)[A-Z]{2,10}-\d+\b/', $upperSubject, $matches) === false) {
            return false;
        }

        foreach ($matches[0] as $candidate) {
            if ($candidate !== $codeNeedle) {
                return true;
            }
        }

        return false;
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

    /**
     * Files touched by a diff, with added/removed line counts and the affected
     * new-file line ranges ("hunks"). `$range` is a git revision range such as
     * `HEAD~1..HEAD` or `develop..HEAD`; null diffs the working tree (staged +
     * unstaged) against HEAD.
     *
     * @return list<array{path: string, added: int, removed: int, hunks: list<string>}>
     */
    public function changeSet(?string $range = null): array
    {
        if (! $this->isRepository()) {
            return [];
        }

        $range = $range !== null && trim($range) !== '' ? trim($range) : null;

        $numstatArgs = $range !== null
            ? ['diff', '--numstat', $range]
            : ['diff', '--numstat', 'HEAD'];
        $hunkArgs = $range !== null
            ? ['diff', '--unified=0', '--no-color', $range]
            : ['diff', '--unified=0', '--no-color', 'HEAD'];

        $numstat = $this->git(...$numstatArgs);
        $hunksByPath = $this->parseHunks($this->git(...$hunkArgs) ?? '');

        $files = [];

        foreach (explode("\n", $numstat ?? '') as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = preg_split('/\t/', $line);

            if (! is_array($parts) || count($parts) < 3) {
                continue;
            }

            [$added, $removed, $path] = $parts;
            $path = trim($path);

            // Rename form: "old => new" or "dir/{old => new}/file".
            if (str_contains($path, ' => ')) {
                $path = trim((string) preg_replace(['/\{.*? => (.*?)\}/', '/^.*? => /'], ['$1', ''], $path));
            }

            $files[] = [
                'path' => $path,
                'added' => $added === '-' ? 0 : (int) $added,
                'removed' => $removed === '-' ? 0 : (int) $removed,
                'hunks' => $hunksByPath[$path] ?? [],
            ];
        }

        return $files;
    }

    /**
     * Parse `git diff --unified=0` output into new-file line ranges per path.
     *
     * @return array<string, list<string>>
     */
    protected function parseHunks(string $diff): array
    {
        $result = [];
        $current = null;

        foreach (explode("\n", $diff) as $line) {
            if (str_starts_with($line, '+++ b/')) {
                $current = substr($line, 6);

                continue;
            }

            if (str_starts_with($line, '+++ ') && str_contains($line, '/dev/null')) {
                $current = null;

                continue;
            }

            if ($current === null || ! str_starts_with($line, '@@')) {
                continue;
            }

            if (preg_match('/\+(\d+)(?:,(\d+))?/', $line, $m) !== 1) {
                continue;
            }

            $start = (int) $m[1];
            $count = isset($m[2]) ? (int) $m[2] : 1;

            if ($count === 0) {
                // Pure deletion — anchor at the surrounding line.
                $result[$current][] = $start.'-'.$start;

                continue;
            }

            $result[$current][] = $start.'-'.($start + $count - 1);
        }

        return $result;
    }

    public function originUrl(): ?string
    {
        if (! $this->isRepository()) {
            return null;
        }

        $remote = $this->git('remote', 'get-url', 'origin');

        return $remote !== null && $remote !== '' ? $remote : null;
    }

    /**
     * @return 'github'|'gitlab'|'bitbucket'|'azure'|null
     */
    public function originProvider(): ?string
    {
        $remote = $this->originUrl();

        if ($remote === null) {
            return null;
        }

        if (preg_match('#(?:^|@|://)(?:[^/]*\.)?github\.com[:/]#i', $remote) === 1) {
            return 'github';
        }

        if (preg_match('#(?:^|@|://)(?:[^/]*\.)?bitbucket\.org[:/]#i', $remote) === 1) {
            return 'bitbucket';
        }

        if (preg_match('#(?:^|@|://)(?:ssh\.)?dev\.azure\.com[:/]#i', $remote) === 1
            || preg_match('#(?:^|@|://)[^/@:]+\.visualstudio\.com[:/]#i', $remote) === 1) {
            return 'azure';
        }

        if (preg_match('#(?:^|@|://)(?:[^/]*\.)?gitlab\.com[:/]#i', $remote) === 1
            || preg_match('#://[^/]*gitlab[^/]*[:/]#i', $remote) === 1
            || preg_match('#@[^:]*gitlab[^:]*:#i', $remote) === 1) {
            return 'gitlab';
        }

        return null;
    }

    public function originRepoSlug(): ?string
    {
        $remote = $this->originUrl();

        if ($remote === null || $remote === '') {
            return null;
        }

        // Azure DevOps carries a three-part identity: organization/project/repo.
        if (preg_match('#dev\.azure\.com[:/]v3/([^/]+)/([^/]+)/([^/]+?)(?:\.git)?/?$#i', $remote, $matches) === 1) {
            return $matches[1].'/'.$matches[2].'/'.$matches[3];
        }

        if (preg_match('#dev\.azure\.com/([^/]+)/([^/]+)/_git/([^/]+?)(?:\.git)?/?$#i', $remote, $matches) === 1) {
            return $matches[1].'/'.$matches[2].'/'.$matches[3];
        }

        if (preg_match('#([^/@]+)\.visualstudio\.com/(?:DefaultCollection/)?([^/]+)/_git/([^/]+?)(?:\.git)?/?$#i', $remote, $matches) === 1) {
            return $matches[1].'/'.$matches[2].'/'.$matches[3];
        }

        if (preg_match('#(?:github\.com|gitlab\.com|bitbucket\.org)[:/]([^/]+)/([^/.]+)(?:\.git)?#i', $remote, $matches) === 1) {
            return $matches[1].'/'.$matches[2];
        }

        // Self-hosted GitLab-style paths: host:group/subgroup/repo.git → last two segments.
        if (preg_match('#[:/]([^/]+)/([^/.]+)(?:\.git)?$#', $remote, $matches) === 1) {
            return $matches[1].'/'.$matches[2];
        }

        return null;
    }

    public function commitUrl(string $sha): ?string
    {
        $remote = $this->originUrl();

        if ($remote === null || $remote === '') {
            return null;
        }

        if (preg_match('#^https?://([^/]+)/(.+?)(?:\.git)?$#i', $remote, $matches) === 1) {
            $host = strtolower($matches[1]);
            $path = trim($matches[2], '/');

            return $this->hostCommitUrl($host, $matches[1], $path, $sha);
        }

        if (preg_match('#^git@([^:]+):(.+?)(?:\.git)?$#', $remote, $matches) === 1) {
            $host = strtolower($matches[1]);
            $path = trim($matches[2], '/');

            return $this->hostCommitUrl($host, $matches[1], $path, $sha);
        }

        return null;
    }

    protected function hostCommitUrl(string $host, string $displayHost, string $path, string $sha): ?string
    {
        if (str_contains($host, 'github.com')) {
            return "https://{$displayHost}/{$path}/commit/{$sha}";
        }

        if (str_contains($host, 'gitlab')) {
            return "https://{$displayHost}/{$path}/-/commit/{$sha}";
        }

        if (str_contains($host, 'bitbucket.org')) {
            return "https://{$displayHost}/{$path}/commits/{$sha}";
        }

        if (str_contains($host, 'dev.azure.com') || str_contains($host, 'visualstudio.com')) {
            // SSH remotes normalise as ssh.dev.azure.com:v3/{org}/{project}/{repo}.
            if (str_starts_with($path, 'v3/')) {
                $segments = explode('/', substr($path, 3));

                if (count($segments) >= 3) {
                    [$org, $project, $repo] = $segments;

                    return "https://dev.azure.com/{$org}/{$project}/_git/{$repo}/commit/{$sha}";
                }
            }

            // https remotes may carry a `{org}@` userinfo prefix on the host.
            $cleanHost = preg_replace('/^[^@]*@/', '', $displayHost) ?? $displayHost;

            return "https://{$cleanHost}/{$path}/commit/{$sha}";
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
