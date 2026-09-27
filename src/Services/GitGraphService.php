<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Illuminate\Support\Carbon;

/**
 * The repository as a picture: every branch with where it stands against
 * the branch it will be merged into, and the recent history drawn as a
 * graph — one lane per line of work, each commit on the branch it was
 * made on.
 *
 * Git does not record which branch a commit was made on. It is worked out
 * here the way a person reads a history: a branch owns what its head
 * reaches through first parents, the main line first, and a merged branch
 * that was deleted is named from the message of the merge that closed it.
 */
class GitGraphService
{
    public const DEFAULT_LIMIT = 150;

    public const MAX_LIMIT = 600;

    /**
     * Branches measured against their target. Each one costs a git call.
     */
    public const MEASURED_BRANCHES = 40;

    /**
     * @var list<string>
     */
    protected const PRIMARY_NAMES = ['main', 'master', 'trunk', 'production'];

    /**
     * @var list<string>
     */
    protected const DEVELOP_NAMES = ['develop', 'development', 'dev'];

    /**
     * Colour groups of the graph. Four hues and a neutral: more would stop
     * being told apart once any two lanes can sit side by side.
     *
     * @var array<string, string>
     */
    public const KINDS = [
        'main' => 'Main line',
        'develop' => 'Integration',
        'work' => 'Work branches',
        'ship' => 'Releases and hotfixes',
        'other' => 'Other',
    ];

    /**
     * @var list<string>|null
     */
    protected ?array $remotes = null;

    protected ?string $commitUrl = null;

    public function __construct(
        protected GitService $git,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function graph(int $limit = self::DEFAULT_LIMIT, ?string $authorEmail = null): array
    {
        $limit = max(20, min(self::MAX_LIMIT, $limit));
        $author = strtolower(trim((string) $authorEmail));

        $empty = [
            'available' => false,
            'primary' => null,
            'develop' => null,
            'current' => null,
            'detached' => false,
            'head' => null,
            'branches' => [],
            'remote_branches' => [],
            'unmeasured' => 0,
            'tags' => [],
            'commits' => [],
            'lanes' => 0,
            'limit' => $limit,
            'total' => 0,
            'truncated' => false,
            'kinds' => [],
            'kind_labels' => self::KINDS,
        ];

        if (! $this->git->isRepository()) {
            return $empty;
        }

        $refs = $this->refs();
        $head = $this->git->read('rev-parse', '--verify', '--quiet', 'HEAD^{commit}');
        $head = is_string($head) && $head !== '' ? $head : null;

        if ($head === null && $refs['heads'] === []) {
            // A repository with nothing committed yet.
            return $empty;
        }

        $current = $this->git->currentBranch();
        $primary = $this->pick($refs['heads'], self::PRIMARY_NAMES) ?? $this->originDefault($refs['heads']);
        $develop = $this->pick($refs['heads'], self::DEVELOP_NAMES);

        if ($primary === null && $current !== null && isset($refs['heads'][$current])) {
            $primary = $current;
        }

        if ($develop === $primary) {
            $develop = null;
        }

        $commits = $this->commits($limit, $head !== null && $current === null);
        $truncated = count($commits) > $limit;
        $commits = array_slice($commits, 0, $limit);

        $owners = $this->assign($commits, $refs, $primary, $develop);
        $labels = $this->labels($refs, $head, $current, $primary, $develop);
        [$rows, $lanes] = $this->layout($commits, $owners, $labels, $primary, $develop, $author);

        $kinds = [];

        foreach ($rows as $row) {
            $kinds[$row['kind']] = ($kinds[$row['kind']] ?? 0) + 1;
        }

        $branches = $this->branches($refs, $primary, $develop, $current);

        return [
            'available' => true,
            'primary' => $primary,
            'develop' => $develop,
            'current' => $current,
            'detached' => $current === null && $head !== null,
            'head' => $head !== null ? substr($head, 0, 7) : null,
            'branches' => $branches['local'],
            'remote_branches' => $branches['remote'],
            'unmeasured' => $branches['unmeasured'],
            'tags' => $this->tags($refs),
            'commits' => $rows,
            'lanes' => $lanes,
            'limit' => $limit,
            'total' => $this->total($head !== null && $current === null),
            'truncated' => $truncated,
            'kinds' => array_intersect_key(array_replace(array_fill_keys(array_keys(self::KINDS), 0), $kinds), $kinds),
            'kind_labels' => self::KINDS,
        ];
    }

    /**
     * What a branch is, going by its name: the colour group it is drawn in
     * and the word shown beside it.
     *
     * @return array{kind: string, type: string}
     */
    public function classify(string $branch, ?string $primary, ?string $develop): array
    {
        $name = $this->withoutRemote($branch);

        if ($primary !== null && $name === $primary) {
            return ['kind' => 'main', 'type' => 'main'];
        }

        if ($develop !== null && $name === $develop) {
            return ['kind' => 'develop', 'type' => 'develop'];
        }

        $prefix = strtolower((string) strstr($name, '/', true));

        return match (true) {
            in_array($prefix, ['feature', 'features', 'feat'], true) => ['kind' => 'work', 'type' => 'feature'],
            in_array($prefix, ['bugfix', 'bug', 'fix'], true) => ['kind' => 'work', 'type' => 'bugfix'],
            in_array($prefix, ['release', 'releases'], true) => ['kind' => 'ship', 'type' => 'release'],
            in_array($prefix, ['hotfix', 'hotfixes'], true) => ['kind' => 'ship', 'type' => 'hotfix'],
            default => ['kind' => 'other', 'type' => $prefix !== '' ? $prefix : 'branch'],
        };
    }

    /**
     * The spec a branch works on, read from its name: `feature/US-004-invoice`.
     */
    public function specCode(string $branch): ?string
    {
        if (preg_match('/(?<![A-Za-z0-9])([A-Za-z]{2,10}-\d+)(?![A-Za-z0-9])/', $this->withoutRemote($branch), $matches) !== 1) {
            return null;
        }

        return strtoupper($matches[1]);
    }

    /**
     * Every head, remote head, and tag, with the commit each one points to.
     *
     * @return array{heads: array<string, array<string, string>>, remotes: array<string, array<string, string>>, tags: array<string, array<string, string>>}
     */
    protected function refs(): array
    {
        $refs = ['heads' => [], 'remotes' => [], 'tags' => []];

        $raw = $this->git->read(
            'for-each-ref',
            '--format=%(refname)%1f%(objectname)%1f%(*objectname)%1f%(upstream:short)%1f%(upstream:track)%1f%(creatordate:iso-strict)%1f%(authorname)%1f%(subject)',
            'refs/heads',
            'refs/remotes',
            'refs/tags'
        );

        foreach (explode("\n", (string) $raw) as $line) {
            $parts = explode("\x1f", $line);

            if (count($parts) < 8) {
                continue;
            }

            [$ref, $object, $peeled, $upstream, $track, $date, $author, $subject] = $parts;

            $entry = [
                'sha' => $peeled !== '' ? $peeled : $object,
                'upstream' => $upstream,
                'track' => trim($track, '[] '),
                'date' => $date,
                'author' => $author,
                'subject' => $subject,
            ];

            if (str_starts_with($ref, 'refs/heads/')) {
                $refs['heads'][substr($ref, 11)] = $entry;
            } elseif (str_starts_with($ref, 'refs/remotes/')) {
                $name = substr($ref, 13);

                // `origin/HEAD` names the default branch; it is not one.
                if (! str_ends_with($name, '/HEAD')) {
                    $refs['remotes'][$name] = $entry;
                }
            } elseif (str_starts_with($ref, 'refs/tags/')) {
                $refs['tags'][substr($ref, 10)] = $entry;
            }
        }

        return $refs;
    }

    /**
     * @param  array<string, array<string, string>>  $heads
     * @param  list<string>  $names
     */
    protected function pick(array $heads, array $names): ?string
    {
        foreach ($names as $name) {
            if (isset($heads[$name])) {
                return $name;
            }
        }

        return null;
    }

    /**
     * The default branch the remote names, when it is checked out here.
     *
     * @param  array<string, array<string, string>>  $heads
     */
    protected function originDefault(array $heads): ?string
    {
        $target = $this->git->read('symbolic-ref', '--quiet', '--short', 'refs/remotes/origin/HEAD');

        if (! is_string($target) || ! str_starts_with($target, 'origin/')) {
            return null;
        }

        $name = substr($target, 7);

        return isset($heads[$name]) ? $name : null;
    }

    /**
     * The newest commits reachable from any branch or tag, children always
     * before their parents. One more than the limit is read, to know
     * whether older history was left out.
     *
     * @return list<array{sha: string, parents: list<string>, author: string, email: string, authored_at: string, subject: string}>
     */
    protected function commits(int $limit, bool $withHead): array
    {
        $args = [
            'log',
            '--date-order',
            '--format=%H%x1f%P%x1f%an%x1f%ae%x1f%aI%x1f%s',
            '--max-count='.($limit + 1),
            '--branches',
            '--tags',
            '--remotes',
        ];

        if ($withHead) {
            $args[] = 'HEAD';
        }

        $commits = [];

        foreach (explode("\n", (string) $this->git->read(...$args)) as $line) {
            $parts = explode("\x1f", $line, 6);

            if (count($parts) < 6 || $parts[0] === '') {
                continue;
            }

            $commits[] = [
                'sha' => $parts[0],
                'parents' => $parts[1] === '' ? [] : explode(' ', $parts[1]),
                'author' => trim($parts[2]) !== '' ? trim($parts[2]) : $parts[3],
                'email' => strtolower(trim($parts[3])),
                'authored_at' => $parts[4],
                'subject' => $parts[5],
            ];
        }

        return $commits;
    }

    protected function total(bool $withHead): int
    {
        $args = ['rev-list', '--count', '--branches', '--tags', '--remotes'];

        if ($withHead) {
            $args[] = 'HEAD';
        }

        return max(0, (int) $this->git->read(...$args));
    }

    /**
     * Which branch each commit belongs to.
     *
     * @param  list<array{sha: string, parents: list<string>, subject: string}>  $commits
     * @param  array{heads: array<string, array<string, string>>, remotes: array<string, array<string, string>>, tags: array<string, array<string, string>>}  $refs
     * @return array<string, array{branch: string|null, kind: string, type: string, gone: bool}>
     */
    protected function assign(array $commits, array $refs, ?string $primary, ?string $develop): array
    {
        $parents = [];

        foreach ($commits as $commit) {
            $parents[$commit['sha']] = $commit['parents'];
        }

        $owners = [];

        $claim = function (string $from, ?string $branch, bool $gone) use (&$owners, $parents, $primary, $develop): void {
            $class = $branch !== null
                ? $this->classify($branch, $primary, $develop)
                : ['kind' => 'other', 'type' => 'branch'];

            // Down the first parents, as far as the history another branch
            // already owns, or the edge of what was read.
            for ($sha = $from; isset($parents[$sha]) && ! isset($owners[$sha]); $sha = $parents[$sha][0] ?? '') {
                $owners[$sha] = ['branch' => $branch, 'kind' => $class['kind'], 'type' => $class['type'], 'gone' => $gone];
            }
        };

        foreach ($this->order($refs, $primary, $develop) as [$branch, $sha]) {
            $claim($sha, $branch, false);
        }

        // A branch merged and deleted left its commits and no name. The
        // merge that closed it usually says what it was called.
        foreach ($commits as $commit) {
            foreach (array_slice($commit['parents'], 1) as $parent) {
                if (isset($parents[$parent]) && ! isset($owners[$parent])) {
                    $claim($parent, $this->mergedBranch($commit['subject']), true);
                }
            }
        }

        foreach ($commits as $commit) {
            if (! isset($owners[$commit['sha']])) {
                $claim($commit['sha'], null, true);
            }
        }

        return $owners;
    }

    /**
     * Heads in the order they claim history: the main line, the
     * integration branch, what ships, then the rest — local before remote,
     * and a remote branch only when no local one carries its name.
     *
     * @param  array{heads: array<string, array<string, string>>, remotes: array<string, array<string, string>>, tags: array<string, array<string, string>>}  $refs
     * @return list<array{0: string, 1: string}>
     */
    protected function order(array $refs, ?string $primary, ?string $develop): array
    {
        $rank = ['main' => 0, 'develop' => 1, 'ship' => 2, 'work' => 3, 'other' => 4];
        $entries = [];

        foreach ($refs['heads'] as $name => $ref) {
            $entries[] = [$rank[$this->classify($name, $primary, $develop)['kind']], 0, $name, $ref['sha']];
        }

        foreach ($refs['remotes'] as $name => $ref) {
            if (! isset($refs['heads'][$this->withoutRemote($name)])) {
                $entries[] = [$rank[$this->classify($name, $primary, $develop)['kind']], 1, $name, $ref['sha']];
            }
        }

        usort($entries, static fn (array $a, array $b): int => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);

        return array_map(static fn (array $entry): array => [$entry[2], $entry[3]], $entries);
    }

    /**
     * The branch a merge commit says it merged, in the words git and the
     * forges write.
     */
    protected function mergedBranch(string $subject): ?string
    {
        $patterns = [
            "/^Merge (?:remote-tracking )?branch '([^']+)'/i",
            '/^Merge (?:remote-tracking )?branch "([^"]+)"/i',
            '/^Merge pull request #\d+ from [^\/\s]+\/(\S+)/i',
            '/^Merged in (\S+) \(pull request #\d+\)/i',
            '/^Merge branch (\S+) into /i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $subject, $matches) === 1) {
                $name = trim($matches[1]);

                return $name !== '' ? $this->withoutRemote($name, true) : null;
            }
        }

        return null;
    }

    /**
     * The labels each commit carries: the branches and tags that point to it.
     *
     * @param  array{heads: array<string, array<string, string>>, remotes: array<string, array<string, string>>, tags: array<string, array<string, string>>}  $refs
     * @return array<string, list<array{type: string, name: string, kind: string|null}>>
     */
    protected function labels(array $refs, ?string $head, ?string $current, ?string $primary, ?string $develop): array
    {
        $labels = [];
        $kind = fn (string $name): string => $this->classify($name, $primary, $develop)['kind'];

        if ($head !== null && $current === null) {
            $labels[$head][] = ['type' => 'head', 'name' => 'HEAD', 'kind' => null];
        }

        foreach ($refs['heads'] as $name => $ref) {
            $labels[$ref['sha']][] = ['type' => $name === $current ? 'current' : 'branch', 'name' => $name, 'kind' => $kind($name)];
        }

        foreach ($refs['tags'] as $name => $ref) {
            $labels[$ref['sha']][] = ['type' => 'tag', 'name' => $name, 'kind' => null];
        }

        foreach ($refs['remotes'] as $name => $ref) {
            $local = $refs['heads'][$this->withoutRemote($name)] ?? null;

            // A remote branch level with its local one adds nothing to read.
            if ($local === null || $local['sha'] !== $ref['sha']) {
                $labels[$ref['sha']][] = ['type' => 'remote', 'name' => $name, 'kind' => $kind($name)];
            }
        }

        // The branch that is checked out leads, tags close the line.
        $order = ['current' => 0, 'head' => 0, 'branch' => 1, 'remote' => 2, 'tag' => 3];

        foreach ($labels as $sha => $list) {
            usort($list, static fn (array $a, array $b): int => [$order[$a['type']], $a['name']] <=> [$order[$b['type']], $b['name']]);
            $labels[$sha] = $list;
        }

        return $labels;
    }

    /**
     * Put every commit on a lane. A lane is a column that waits for one
     * commit: the parent of the last commit drawn on it. The main line
     * keeps the first column and the integration branch the second, so
     * the picture reads the same from one repository to the next.
     *
     * @param  list<array{sha: string, parents: list<string>, author: string, email: string, authored_at: string, subject: string}>  $commits
     * @param  array<string, array{branch: string|null, kind: string, type: string, gone: bool}>  $owners
     * @param  array<string, list<array{type: string, name: string, kind: string|null}>>  $labels
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    protected function layout(array $commits, array $owners, array $labels, ?string $primary, ?string $develop, string $author): array
    {
        $reserved = [];

        foreach ([$primary, $develop] as $name) {
            if ($name !== null) {
                $reserved[$name] = count($reserved);
            }
        }

        $floor = count($reserved);

        /** @var array<int, array{sha: string, kind: string}|null> $lanes */
        $lanes = [];
        $rows = [];
        $width = 0;

        $free = static function () use (&$lanes, $floor): int {
            for ($index = $floor; ; $index++) {
                if (! isset($lanes[$index])) {
                    return $index;
                }
            }
        };

        $waitingFor = static function (string $sha) use (&$lanes): array {
            $found = [];

            foreach ($lanes as $index => $lane) {
                if ($lane !== null && $lane['sha'] === $sha) {
                    $found[] = $index;
                }
            }

            sort($found);

            return $found;
        };

        foreach ($commits as $commit) {
            $sha = $commit['sha'];
            $owner = $owners[$sha] ?? ['branch' => null, 'kind' => 'other', 'type' => 'branch', 'gone' => true];
            $waiting = $waitingFor($sha);
            $own = $owner['branch'] !== null && ! $owner['gone'] ? ($reserved[$owner['branch']] ?? null) : null;

            if ($own !== null && (! isset($lanes[$own]) || $lanes[$own]['sha'] === $sha)) {
                $column = $own;
            } elseif ($waiting !== []) {
                $column = $waiting[0];
            } else {
                $column = $free();
            }

            $in = [];

            foreach ($waiting as $index) {
                $in[] = ['lane' => $index, 'kind' => $lanes[$index]['kind']];
                $lanes[$index] = null;
            }

            $through = [];

            foreach ($lanes as $index => $lane) {
                if ($lane !== null) {
                    $through[] = ['lane' => $index, 'kind' => $lane['kind']];
                }
            }

            $out = [];

            foreach ($commit['parents'] as $position => $parent) {
                if ($position === 0) {
                    $lanes[$column] = ['sha' => $parent, 'kind' => $owner['kind']];
                    $out[] = ['lane' => $column, 'kind' => $owner['kind']];

                    continue;
                }

                $joined = $waitingFor($parent);
                // The first parent now waits on this column; a merge of the
                // same commit twice would find it there.
                $target = $joined !== [] ? $joined[0] : $free();

                if ($joined === []) {
                    $lanes[$target] = ['sha' => $parent, 'kind' => $owners[$parent]['kind'] ?? $owner['kind']];
                }

                $out[] = ['lane' => $target, 'kind' => $lanes[$target]['kind']];
            }

            $used = array_merge([$column], array_column($in, 'lane'), array_column($through, 'lane'), array_column($out, 'lane'));
            $width = max($width, max($used) + 1);

            try {
                $when = Carbon::parse($commit['authored_at']);
            } catch (\Throwable) {
                $when = null;
            }

            $rows[] = [
                'sha' => $sha,
                'short_sha' => substr($sha, 0, 7),
                'subject' => $commit['subject'],
                'author' => $commit['author'],
                'email' => $commit['email'],
                'authored_at' => $commit['authored_at'],
                'date_label' => $when?->format('M j, Y') ?? '',
                'time_label' => $when?->format('H:i') ?? '',
                'ago' => $when?->diffForHumans() ?? '',
                'is_merge' => count($commit['parents']) > 1,
                'is_root' => $commit['parents'] === [],
                'branch' => $owner['branch'],
                'kind' => $owner['kind'],
                'type' => $owner['type'],
                'gone' => $owner['gone'],
                // The branch says which spec the work belongs to; on a shared
                // branch the message of the commit does.
                'spec' => ($owner['branch'] !== null ? $this->specCode($owner['branch']) : null) ?? $this->specCode($commit['subject']),
                'refs' => $labels[$sha] ?? [],
                'lane' => $column,
                'in' => $in,
                'through' => $through,
                'out' => $out,
                'url' => $this->commitUrl($sha),
                'dim' => $author !== '' && $commit['email'] !== $author,
            ];
        }

        return [$rows, $width];
    }

    /**
     * @param  array{heads: array<string, array<string, string>>, remotes: array<string, array<string, string>>, tags: array<string, array<string, string>>}  $refs
     * @return array{local: list<array<string, mixed>>, remote: list<array<string, mixed>>, unmeasured: int}
     */
    protected function branches(array $refs, ?string $primary, ?string $develop, ?string $current): array
    {
        $rank = ['main' => 0, 'develop' => 1, 'ship' => 2, 'work' => 3, 'other' => 4];
        $local = [];

        foreach ($refs['heads'] as $name => $ref) {
            $class = $this->classify($name, $primary, $develop);
            $local[] = array_merge($this->describe($name, $ref), $class, [
                'current' => $name === $current,
                'target' => $this->target($name, $class['type'], $primary, $develop),
                'upstream' => $ref['upstream'] !== '' ? $ref['upstream'] : null,
                'upstream_state' => $this->upstreamState($ref),
                'ahead' => null,
                'behind' => null,
                'state' => null,
                'spec' => $this->specCode($name),
            ]);
        }

        // The lines everything is measured against first, then the newest work.
        usort($local, static function (array $a, array $b) use ($rank): int {
            $left = min($rank[$a['kind']], 2);
            $right = min($rank[$b['kind']], 2);

            return [$left, $b['timestamp']] <=> [$right, $a['timestamp']];
        });

        $measured = 0;

        foreach ($local as $index => $branch) {
            if ($branch['target'] === null) {
                continue;
            }

            if ($measured >= self::MEASURED_BRANCHES) {
                continue;
            }

            $measured++;
            $counts = $this->git->read('rev-list', '--left-right', '--count', $branch['target'].'...'.$branch['name']);

            if (! is_string($counts) || preg_match('/^(\d+)\s+(\d+)$/', trim($counts), $matches) !== 1) {
                continue;
            }

            $local[$index]['behind'] = (int) $matches[1];
            $local[$index]['ahead'] = (int) $matches[2];
            $local[$index]['state'] = $this->state((int) $matches[2], (int) $matches[1], $branch['target']);
        }

        $unmeasured = count(array_filter($local, static fn (array $branch): bool => $branch['target'] !== null && $branch['ahead'] === null));
        $remote = [];

        foreach ($refs['remotes'] as $name => $ref) {
            if (isset($refs['heads'][$this->withoutRemote($name)])) {
                continue;
            }

            $remote[] = array_merge($this->describe($name, $ref), $this->classify($name, $primary, $develop), [
                'spec' => $this->specCode($name),
            ]);
        }

        usort($remote, static fn (array $a, array $b): int => $b['timestamp'] <=> $a['timestamp']);

        return ['local' => $local, 'remote' => $remote, 'unmeasured' => $unmeasured];
    }

    /**
     * @param  array<string, string>  $ref
     * @return array<string, mixed>
     */
    protected function describe(string $name, array $ref): array
    {
        try {
            $when = $ref['date'] !== '' ? Carbon::parse($ref['date']) : null;
        } catch (\Throwable) {
            $when = null;
        }

        return [
            'name' => $name,
            'sha' => $ref['sha'],
            'short_sha' => substr($ref['sha'], 0, 7),
            'subject' => $ref['subject'],
            'author' => $ref['author'],
            'date' => $when?->toIso8601String() ?? '',
            'timestamp' => $when?->getTimestamp() ?? 0,
            'date_label' => $when?->format('M j, Y') ?? '',
            'ago' => $when?->diffForHumans() ?? '',
            'url' => $this->commitUrl($ref['sha']),
        ];
    }

    /**
     * Where a commit can be read on the forge. The remote is asked once:
     * every commit of the page shares the same address but for its hash.
     */
    protected function commitUrl(string $sha): ?string
    {
        $this->commitUrl ??= (string) $this->git->commitUrl('__SHA__');

        return $this->commitUrl === '' ? null : str_replace('__SHA__', $sha, $this->commitUrl);
    }

    /**
     * The branch a branch is heading for: work goes to the integration
     * branch, everything that ships goes to the main line.
     */
    protected function target(string $name, string $type, ?string $primary, ?string $develop): ?string
    {
        if ($name === $primary) {
            return null;
        }

        if ($name === $develop || in_array($type, ['release', 'hotfix'], true)) {
            return $primary;
        }

        return $develop ?? $primary;
    }

    /**
     * @return array{key: string, label: string}
     */
    protected function state(int $ahead, int $behind, string $target): array
    {
        $commits = static fn (int $count): string => $count.' '.($count === 1 ? 'commit' : 'commits');

        return match (true) {
            $ahead === 0 && $behind === 0 => ['key' => 'level', 'label' => 'Level with '.$target],
            $ahead === 0 => ['key' => 'merged', 'label' => 'Merged into '.$target],
            $behind === 0 => ['key' => 'ahead', 'label' => $commits($ahead).' to merge into '.$target],
            default => ['key' => 'diverged', 'label' => $commits($ahead).' to merge, '.$behind.' behind '.$target],
        };
    }

    /**
     * @param  array<string, string>  $ref
     * @return array{key: string, label: string}
     */
    protected function upstreamState(array $ref): array
    {
        if ($ref['upstream'] === '') {
            return ['key' => 'local', 'label' => 'Not pushed — only on this machine'];
        }

        $track = $ref['track'];

        if ($track === 'gone') {
            return ['key' => 'gone', 'label' => 'Deleted on the remote'];
        }

        if ($track === '') {
            return ['key' => 'synced', 'label' => 'In step with '.$ref['upstream']];
        }

        $ahead = preg_match('/ahead (\d+)/', $track, $matches) === 1 ? (int) $matches[1] : 0;
        $behind = preg_match('/behind (\d+)/', $track, $matches) === 1 ? (int) $matches[1] : 0;
        $parts = [];

        if ($ahead > 0) {
            $parts[] = $ahead.' to push';
        }

        if ($behind > 0) {
            $parts[] = $behind.' to pull';
        }

        return [
            'key' => $ahead > 0 && $behind > 0 ? 'diverged' : ($ahead > 0 ? 'push' : 'pull'),
            'label' => implode(', ', $parts).' — '.$ref['upstream'],
        ];
    }

    /**
     * @param  array{heads: array<string, array<string, string>>, remotes: array<string, array<string, string>>, tags: array<string, array<string, string>>}  $refs
     * @return list<array<string, mixed>>
     */
    protected function tags(array $refs): array
    {
        $tags = [];

        foreach ($refs['tags'] as $name => $ref) {
            $tags[] = $this->describe($name, $ref);
        }

        usort($tags, static fn (array $a, array $b): int => [$b['timestamp'], $b['name']] <=> [$a['timestamp'], $a['name']]);

        return $tags;
    }

    /**
     * `origin/feature/x` is `feature/x`. A name quoted in a merge message
     * may carry the remote too; a local name that merely contains a slash
     * (`feature/x`) must be left alone, so only known remotes are cut there.
     */
    protected function withoutRemote(string $name, bool $fromMessage = false): string
    {
        $this->remotes ??= array_values(array_filter(explode("\n", (string) $this->git->read('remote'))));

        foreach ($this->remotes as $remote) {
            if (str_starts_with($name, $remote.'/')) {
                return substr($name, strlen($remote) + 1);
            }
        }

        if ($fromMessage && str_starts_with($name, 'origin/')) {
            return substr($name, 7);
        }

        return $name;
    }
}
