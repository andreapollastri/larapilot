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

    /**
     * Local git contribution calendar: every commit on every local branch
     * for the last 12 months, optionally filtered by author email.
     *
     * @return array{
     *     is_repository: bool,
     *     origin_url: string|null,
     *     origin_provider: string|null,
     *     origin_slug: string|null,
     *     branches: list<string>,
     *     authors: list<array{email: string, name: string, commits: int}>,
     *     selected_author: string|null,
     *     total: int,
     *     max: int,
     *     range_start: string,
     *     range_end: string,
     *     weeks: list<list<array{date: string, count: int, in_range: bool, level: int, title: string}>>,
     *     months: list<array{label: string, offset: int, span: int}>
     * }
     */
    public function contributionActivity(?string $authorEmail = null): array
    {
        $today = new \DateTimeImmutable('today');
        $rangeEnd = $today;
        $rangeStart = $today->modify('-1 year')->modify('+1 day');
        $selected = $this->normalizeAuthorEmail($authorEmail);

        $empty = [
            'is_repository' => false,
            'origin_url' => null,
            'origin_provider' => null,
            'origin_slug' => null,
            'branches' => [],
            'authors' => [],
            'selected_author' => $selected,
            'total' => 0,
            'max' => 0,
            'range_start' => $rangeStart->format('Y-m-d'),
            'range_end' => $rangeEnd->format('Y-m-d'),
            'weeks' => $this->emptyContributionWeeks($rangeStart, $rangeEnd),
            'months' => [],
        ];
        $empty['months'] = $this->contributionMonthLabels($empty['weeks']);

        if (! $this->isRepository()) {
            return $empty;
        }

        $empty['is_repository'] = true;
        $empty['origin_url'] = $this->originUrl();
        $empty['origin_provider'] = $this->originProvider();
        $empty['origin_slug'] = $this->originRepoSlug();
        $empty['branches'] = $this->localBranches();

        $commits = $this->commitsSince($rangeStart);
        $authors = [];
        $counts = [];

        foreach ($commits as $commit) {
            $day = $commit['date'];

            if ($day < $rangeStart->format('Y-m-d') || $day > $rangeEnd->format('Y-m-d')) {
                continue;
            }

            $email = $commit['email'];

            if (! isset($authors[$email])) {
                $authors[$email] = [
                    'email' => $email,
                    'name' => $commit['name'],
                    'commits' => 0,
                ];
            }

            $authors[$email]['commits']++;

            if ($authors[$email]['name'] === '' && $commit['name'] !== '') {
                $authors[$email]['name'] = $commit['name'];
            }

            if ($selected !== null && $email !== $selected) {
                continue;
            }

            $counts[$day] = ($counts[$day] ?? 0) + 1;
        }

        uasort($authors, function (array $left, array $right): int {
            return $right['commits'] <=> $left['commits']
                ?: strcasecmp($left['name'], $right['name']);
        });

        $authorList = array_values($authors);

        if ($selected !== null && ! isset($authors[$selected])) {
            $selected = null;
            $counts = [];

            foreach ($commits as $commit) {
                $day = $commit['date'];

                if ($day < $rangeStart->format('Y-m-d') || $day > $rangeEnd->format('Y-m-d')) {
                    continue;
                }

                $counts[$day] = ($counts[$day] ?? 0) + 1;
            }
        }

        $weeks = $this->buildContributionWeeks($rangeStart, $rangeEnd, $counts);

        return [
            'is_repository' => true,
            'origin_url' => $empty['origin_url'],
            'origin_provider' => $empty['origin_provider'],
            'origin_slug' => $empty['origin_slug'],
            'branches' => $empty['branches'],
            'authors' => $authorList,
            'selected_author' => $selected,
            'total' => array_sum($counts),
            'max' => $counts === [] ? 0 : max($counts),
            'range_start' => $rangeStart->format('Y-m-d'),
            'range_end' => $rangeEnd->format('Y-m-d'),
            'weeks' => $weeks,
            'months' => $this->contributionMonthLabels($weeks),
        ];
    }

    /**
     * @return list<string>
     */
    protected function localBranches(): array
    {
        $output = $this->git('for-each-ref', '--format=%(refname:short)', 'refs/heads');

        if ($output === null || $output === '') {
            return [];
        }

        $branches = [];

        foreach (explode("\n", $output) as $line) {
            $name = trim($line);

            if ($name !== '') {
                $branches[] = $name;
            }
        }

        return $branches;
    }

    /**
     * @return list<array{email: string, name: string, date: string}>
     */
    protected function commitsSince(\DateTimeImmutable $since): array
    {
        // Do not pass --since to git: it stops walking a lineage at the first
        // older commit, so a backdated HEAD hides newer ancestors. Filter dates
        // in PHP after reading every local-branch tip.
        $log = $this->git(
            'log',
            '--all',
            '--format=%ae%x1f%an%x1f%aI',
            '--max-count=20000'
        );

        if ($log === null || $log === '') {
            return [];
        }

        $commits = [];

        foreach (explode("\n", $log) as $line) {
            if ($line === '') {
                continue;
            }

            $parts = explode("\x1f", $line);

            if (count($parts) < 3) {
                continue;
            }

            [$email, $name, $authoredAt] = $parts;
            $email = $this->normalizeAuthorEmail($email) ?? 'unknown';

            try {
                $authored = new \DateTimeImmutable($authoredAt);
            } catch (\Exception $e) {
                continue;
            }

            $day = $authored->format('Y-m-d');

            if ($day < $since->format('Y-m-d')) {
                continue;
            }

            $commits[] = [
                'email' => $email,
                'name' => trim($name) !== '' ? trim($name) : $email,
                'date' => $day,
            ];
        }

        return $commits;
    }

    protected function normalizeAuthorEmail(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));

        return $email !== '' ? $email : null;
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<list<array{date: string, count: int, in_range: bool, level: int, title: string}>>
     */
    protected function buildContributionWeeks(
        \DateTimeImmutable $rangeStart,
        \DateTimeImmutable $rangeEnd,
        array $counts,
    ): array {
        $gridStart = $rangeStart;

        while ((int) $gridStart->format('w') !== 0) {
            $gridStart = $gridStart->modify('-1 day');
        }

        $gridEnd = $rangeEnd;

        while ((int) $gridEnd->format('w') !== 6) {
            $gridEnd = $gridEnd->modify('+1 day');
        }

        $max = $counts === [] ? 0 : max($counts);
        $weeks = [];
        $cursor = $gridStart;

        while ($cursor <= $gridEnd) {
            $week = [];

            for ($i = 0; $i < 7; $i++) {
                $date = $cursor->format('Y-m-d');
                $inRange = $cursor >= $rangeStart && $cursor <= $rangeEnd;
                $count = $inRange ? ($counts[$date] ?? 0) : 0;
                $level = $inRange ? $this->contributionLevel($count, $max) : 0;

                $week[] = [
                    'date' => $date,
                    'count' => $count,
                    'in_range' => $inRange,
                    'level' => $level,
                    'title' => $this->contributionTitle($count, $cursor, $inRange),
                ];

                $cursor = $cursor->modify('+1 day');
            }

            $weeks[] = $week;
        }

        return $weeks;
    }

    /**
     * @return list<list<array{date: string, count: int, in_range: bool, level: int, title: string}>>
     */
    protected function emptyContributionWeeks(\DateTimeImmutable $rangeStart, \DateTimeImmutable $rangeEnd): array
    {
        return $this->buildContributionWeeks($rangeStart, $rangeEnd, []);
    }

    /**
     * @param  list<list<array{date: string, count: int, in_range: bool, level: int, title: string}>>  $weeks
     * @return list<array{label: string, offset: int, span: int}>
     */
    protected function contributionMonthLabels(array $weeks): array
    {
        $labels = [];
        $current = null;
        $offset = 0;
        $span = 0;

        foreach ($weeks as $index => $week) {
            $month = null;

            foreach ($week as $day) {
                if ($day['in_range']) {
                    $month = (new \DateTimeImmutable($day['date']))->format('M');
                    break;
                }
            }

            $month ??= (new \DateTimeImmutable($week[0]['date']))->format('M');

            if ($current === null) {
                $current = $month;
                $offset = $index;
                $span = 1;

                continue;
            }

            if ($month !== $current) {
                $labels[] = ['label' => $current, 'offset' => $offset, 'span' => $span];
                $current = $month;
                $offset = $index;
                $span = 1;

                continue;
            }

            $span++;
        }

        if ($current !== null) {
            $labels[] = ['label' => $current, 'offset' => $offset, 'span' => $span];
        }

        return $labels;
    }

    protected function contributionLevel(int $count, int $max): int
    {
        if ($count <= 0 || $max <= 0) {
            return 0;
        }

        return (int) max(1, min(4, (int) ceil(($count / $max) * 4)));
    }

    protected function contributionTitle(int $count, \DateTimeImmutable $day, bool $inRange): string
    {
        $label = $day->format('F jS');

        if (! $inRange) {
            return $label;
        }

        if ($count === 0) {
            return 'No contributions on '.$label.'.';
        }

        $noun = $count === 1 ? 'contribution' : 'contributions';

        return number_format($count).' '.$noun.' on '.$label.'.';
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

    /**
     * Semver tags from the repository (`vX.Y.Z` or `X.Y.Z`).
     *
     * @return list<array{version: string, tagged_at: string|null, subject: string|null}>
     */
    public function semverTags(bool $includeAnnotated = false): array
    {
        if (! $this->isRepository()) {
            return [];
        }

        $format = $includeAnnotated ? '%(refname:short)%x1f%(creatordate:iso-strict)%x1f%(contents:subject)' : '%(refname:short)%x1f%(creatordate:iso-strict)';
        $raw = $this->git('tag', '-l', '--sort=version:refname', '--format='.$format);

        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $tags = [];

        foreach (explode("\n", $raw) as $line) {
            if ($line === '') {
                continue;
            }

            $parts = explode("\x1f", $line);
            $name = trim($parts[0] ?? '');

            if ($name === '') {
                continue;
            }

            $version = ltrim($name, 'vV');

            if (preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/', $version) !== 1) {
                continue;
            }

            $tags[] = [
                'version' => $version,
                'tagged_at' => isset($parts[1]) && trim($parts[1]) !== '' ? trim($parts[1]) : null,
                'subject' => isset($parts[2]) && trim($parts[2]) !== '' ? trim($parts[2]) : null,
            ];
        }

        return $tags;
    }

    /**
     * @return list<string>
     */
    public function releaseBranches(): array
    {
        if (! $this->isRepository()) {
            return [];
        }

        $raw = $this->git('branch', '-a', '--list', 'release/*');

        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $branches = [];

        foreach (explode("\n", $raw) as $line) {
            $branch = trim(str_replace('*', '', $line));

            if ($branch === '' || ! str_contains($branch, 'release/')) {
                continue;
            }

            $branch = preg_replace('#^remotes/[^/]+/#', '', $branch) ?? $branch;
            $branches[] = $branch;
        }

        return array_values(array_unique($branches));
    }

    public function currentBranch(): ?string
    {
        if (! $this->isRepository()) {
            return null;
        }

        $result = $this->run('branch', '--show-current');
        $branch = trim($result['output']);

        return $result['ok'] && $branch !== '' ? $branch : null;
    }

    public function localBranchExists(string $branch): bool
    {
        if (! $this->isRepository() || $branch === '') {
            return false;
        }

        return $this->run('show-ref', '--verify', '--quiet', 'refs/heads/'.$branch)['ok'];
    }

    public function workingTreeClean(): bool
    {
        if (! $this->isRepository()) {
            return false;
        }

        // Untracked files ride along on checkout. Only staged or unstaged
        // edits to tracked files block a branch switch or a merge.
        $result = $this->run('status', '--porcelain', '--untracked-files=no');

        return $result['ok'] && trim($result['output']) === '';
    }

    /**
     * @return array{ok: bool, code: int, output: string}
     */
    public function run(string ...$args): array
    {
        $command = array_merge(['git', '-C', $this->config->projectRoot()], $args);
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (! is_resource($process)) {
            return ['ok' => false, 'code' => 1, 'output' => 'Unable to run git.'];
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        $output = trim(implode("\n", array_filter([
            is_string($stdout) ? trim($stdout) : '',
            is_string($stderr) ? trim($stderr) : '',
        ], static fn (string $part): bool => $part !== '')));

        return [
            'ok' => $code === 0,
            'code' => $code,
            'output' => $output,
        ];
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
