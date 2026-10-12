<?php

declare(strict_types=1);

namespace Larapilot\Services;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Str;
use Larapilot\Support\AtomicFile;
use Larapilot\Support\PlanDate;
use Symfony\Component\Yaml\Yaml;

/**
 * The product roadmap as the outside may read it: the releases of the
 * ledger — shipped, under way, next, later — and the stories each holds,
 * by their titles alone. No code, no name, no hour, no cost, no branch:
 * what a customer is told, not how the team works.
 *
 * `.larapilot/roadmap.yaml` curates the wording: an intro, a public title
 * and a one-line summary for a version or a story, a story kept out of
 * sight, and the token of the public link.
 */
class RoadmapService
{
    public const TOKEN_LENGTH = 40;

    /**
     * @var list<string>
     */
    public const LANES = ['shipped', 'now', 'next', 'later'];

    /**
     * What the public page never carries, said on the team page so nobody
     * has to guess.
     *
     * @var list<string>
     */
    public const LEFT_OUT = [
        'Spec and epic codes',
        'People — authors, assignees, reviewers',
        'Hours, story points, costs, tokens',
        'Git branches, tags, and commits',
        'Comments, blockers, and rework notes',
        'Mockups and their links',
        'Stories a release does not claim, and the ones hidden here',
    ];

    public function __construct(
        protected ConfigService $config,
        protected ReleaseService $releases,
        protected SpecService $specs,
        protected PrdService $prd,
        protected UsageService $usage,
    ) {}

    public function path(): string
    {
        $config = $this->config->resolve();

        return $this->config->absolutePath($config['paths']['roadmap'] ?? '.larapilot/roadmap.yaml');
    }

    /**
     * Whether the public link is on (`settings.public_roadmap`).
     */
    public function enabled(): bool
    {
        return $this->config->publicRoadmapEnabled();
    }

    /**
     * The curated wording and the public token, as the file holds them.
     *
     * @return array{intro: string|null, public_token: string|null, versions: array<string, array<string, mixed>>, updated_at: string|null}
     */
    public function read(): array
    {
        $empty = ['intro' => null, 'public_token' => null, 'versions' => [], 'updated_at' => null];
        $path = $this->path();

        if (! is_file($path)) {
            return $empty;
        }

        $parsed = Yaml::parseFile($path);

        if (! is_array($parsed)) {
            return $empty;
        }

        $versions = [];

        foreach (is_array($parsed['versions'] ?? null) ? $parsed['versions'] : [] as $version => $entry) {
            if (is_array($entry)) {
                $versions[ltrim((string) $version, 'vV')] = $entry;
            }
        }

        return [
            'intro' => is_string($parsed['intro'] ?? null) && trim($parsed['intro']) !== '' ? trim($parsed['intro']) : null,
            'public_token' => is_string($parsed['public_token'] ?? null) && $parsed['public_token'] !== '' ? $parsed['public_token'] : null,
            'versions' => $versions,
            'updated_at' => is_string($parsed['updated_at'] ?? null) ? $parsed['updated_at'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function write(array $data): void
    {
        $path = $this->path();
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $data['updated_at'] = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);

        AtomicFile::write($path, Yaml::dump($data, 5, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    }

    /**
     * Where this host keeps the token of its public link: beside the
     * roadmap, out of Git. Each server has its own link, and making a new
     * one never dirties the working tree a deploy resets.
     */
    public function tokenPath(): string
    {
        return $this->config->absolutePath('.larapilot/roadmap-link.yaml');
    }

    /**
     * The token of the public link, when one was made. A token written in
     * `roadmap.yaml` before 5.1 is still read, until a new one is made.
     */
    public function token(): ?string
    {
        $path = $this->tokenPath();

        if (is_file($path)) {
            try {
                $parsed = Yaml::parseFile($path);
            } catch (\Throwable) {
                $parsed = null;
            }

            if (is_array($parsed) && is_string($parsed['token'] ?? null) && strlen($parsed['token']) >= 20) {
                return $parsed['token'];
            }
        }

        return $this->read()['public_token'];
    }

    /**
     * The token of the public link, made now when there is none yet.
     */
    public function ensureToken(): string
    {
        return $this->token() ?? $this->regenerateToken();
    }

    /**
     * A new token: the old link stops answering at once.
     */
    public function regenerateToken(): string
    {
        $token = Str::random(self::TOKEN_LENGTH);

        AtomicFile::write($this->tokenPath(), "# The token of the public roadmap link of this host. Never commit it: each server has its own.\n".Yaml::dump(['token' => $token, 'made_at' => now()->toIso8601String()], 2, 2));
        $this->ensureGitignored();

        // A token of before 5.1 lived in the committed roadmap.yaml: it goes.
        $data = $this->read();

        if ($data['public_token'] !== null) {
            $data['public_token'] = null;
            $this->write(array_filter($data, static fn (mixed $value): bool => $value !== null));
        }

        return $token;
    }

    protected function ensureGitignored(): void
    {
        $relative = '.larapilot/roadmap-link.yaml';
        $gitignore = rtrim($this->config->projectRoot(), '/\\').'/.gitignore';
        $existing = is_file($gitignore) ? (string) file_get_contents($gitignore) : '';

        foreach (preg_split('/\r\n|\r|\n/', $existing) ?: [] as $line) {
            if (in_array(trim($line), [$relative, '/'.$relative], true)) {
                return;
            }
        }

        $prefix = ($existing !== '' && ! str_ends_with($existing, "\n")) ? "\n" : '';
        @file_put_contents($gitignore, $existing.$prefix."\n# Larapilot public roadmap link of this host — never commit\n/".$relative."\n");
    }

    /**
     * Whether a token is the one of the public link — compared in constant time.
     */
    public function matchesToken(string $token): bool
    {
        $known = $this->token();

        return $known !== null && hash_equals($known, $token);
    }

    /**
     * The public link, when the page is on and a token exists.
     */
    public function publicUrl(): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $token = $this->token();

        if ($token === null || ! app('router')->has('larapilot.roadmap.public')) {
            return null;
        }

        return route('larapilot.roadmap.public', ['token' => $token]);
    }

    /**
     * The name of the product: the title of the PRD, the name of the
     * application when there is none.
     */
    public function productName(): string
    {
        $prd = $this->prd->read();

        if (is_string($prd) && preg_match('/^#\s+(.+)$/m', $prd, $matches) === 1) {
            $title = trim((string) preg_replace('/[*_`]/', '', $matches[1]));

            // "Fernway — Product Requirements Document" names Fernway; a
            // heading that is only the kind of document names nothing.
            $title = trim((string) preg_replace('/\s*[—–:|-]+\s*(product requirements(\s+document)?|prd)\s*$/iu', '', $title));

            if ($title !== '' && preg_match('/^(product requirements(\s+document)?|prd)$/i', $title) !== 1) {
                return $title;
            }
        }

        $app = trim((string) config('app.name', ''));

        return $app !== '' && $app !== 'Laravel' ? $app : 'Product roadmap';
    }

    /**
     * The roadmap as the public page shows it.
     *
     * @return array{
     *     product: string,
     *     intro: string|null,
     *     empty: bool,
     *     lanes: list<array{key: string, label: string, hint: string, versions: list<array<string, mixed>>}>,
     *     versions: list<array<string, mixed>>,
     *     updated_at: string
     * }
     */
    public function build(): array
    {
        $curated = $this->read();
        $milestones = $this->milestones();
        $index = [];

        foreach ($this->specs->allSpecs() as $spec) {
            $index[strtoupper((string) ($spec['code'] ?? ''))] = $spec;
        }

        $versions = [];

        foreach ($this->releases->list() as $release) {
            $version = (string) ($release['version'] ?? '');

            if ($version === '') {
                continue;
            }

            $override = $curated['versions'][$version] ?? [];

            if (($override['hidden'] ?? false) === true) {
                continue;
            }

            $status = strtolower((string) ($release['status'] ?? 'planned'));
            $items = $this->items($release, $override, $index);

            $versions[] = [
                'version' => $version,
                'label' => 'v'.$version,
                'title' => $this->text($override['title'] ?? null) ?? trim((string) ($release['title'] ?? '')),
                'summary' => $this->text($override['summary'] ?? null),
                'status' => $status,
                'status_label' => match ($status) {
                    'shipped' => 'Shipped',
                    'in_progress' => 'In progress',
                    default => 'Planned',
                },
                'target' => $this->target($status, $override, $release, $milestones[$version] ?? null),
                'groups' => $items['groups'],
                'count' => $items['count'],
                'hidden' => $items['hidden'],
            ];
        }

        $shipped = array_values(array_filter($versions, static fn (array $row): bool => $row['status'] === 'shipped'));
        $now = array_values(array_filter($versions, static fn (array $row): bool => $row['status'] === 'in_progress'));
        $planned = array_values(array_filter($versions, static fn (array $row): bool => $row['status'] === 'planned'));

        // The newest release first: what shipped last is what the reader asks about.
        $shipped = array_reverse($shipped);

        $lanes = [
            ['key' => 'shipped', 'label' => 'Shipped', 'hint' => 'What is out', 'versions' => $shipped],
            ['key' => 'now', 'label' => 'Now', 'hint' => 'Being built', 'versions' => $now],
            ['key' => 'next', 'label' => 'Next', 'hint' => 'The following release', 'versions' => array_slice($planned, 0, 1)],
            ['key' => 'later', 'label' => 'Later', 'hint' => 'On the horizon', 'versions' => array_slice($planned, 1)],
        ];

        return [
            'product' => $this->productName(),
            'intro' => $curated['intro'],
            'empty' => $versions === [],
            'lanes' => $lanes,
            'versions' => $versions,
            'updated_at' => $this->updatedAt($curated),
        ];
    }

    /**
     * Curate the wording: the intro, a version, or one story of a version.
     * Only the keys given change; an empty string clears one.
     *
     * @param  array{intro?: string|null, version?: string, title?: string|null, summary?: string|null, target?: string|null, hidden?: bool|null, item?: string}  $changes
     * @return array{intro: string|null, public_token: string|null, versions: array<string, array<string, mixed>>, updated_at: string|null}
     *
     * @throws \InvalidArgumentException
     */
    public function curate(array $changes): array
    {
        $data = $this->read();

        if (array_key_exists('intro', $changes)) {
            $intro = trim((string) $changes['intro']);
            $data['intro'] = $intro !== '' ? $intro : null;
        }

        if (isset($changes['version'])) {
            $version = $this->releases->normalizeVersion((string) $changes['version']);
            $release = $this->releases->find($version);

            if ($release === null) {
                throw new \InvalidArgumentException("Release {$version} is not in the ledger.");
            }

            $entry = is_array($data['versions'][$version] ?? null) ? $data['versions'][$version] : [];

            if (isset($changes['item'])) {
                $code = strtoupper(trim((string) $changes['item']));
                $claimed = array_map(static fn (mixed $spec): string => strtoupper(trim((string) $spec)), is_array($release['specs'] ?? null) ? $release['specs'] : []);

                if (! in_array($code, $claimed, true)) {
                    throw new \InvalidArgumentException("Release {$version} does not hold {$code}.");
                }

                $items = is_array($entry['items'] ?? null) ? $entry['items'] : [];
                $items[$code] = $this->apply(is_array($items[$code] ?? null) ? $items[$code] : [], $changes, ['title', 'summary', 'hidden']);
                $entry['items'] = $items;
            } else {
                $entry = $this->apply($entry, $changes, ['title', 'summary', 'target', 'hidden']) + array_filter(['items' => $entry['items'] ?? null]);
            }

            $data['versions'][$version] = $entry;
        }

        $this->write($data);

        return $this->read();
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $changes
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    protected function apply(array $entry, array $changes, array $keys): array
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $changes) || $changes[$key] === null) {
                continue;
            }

            if ($key === 'hidden') {
                if ($changes['hidden'] === true) {
                    $entry['hidden'] = true;
                } else {
                    unset($entry['hidden']);
                }

                continue;
            }

            $value = trim((string) $changes[$key]);

            if ($value === '') {
                unset($entry[$key]);
            } else {
                $entry[$key] = $value;
            }
        }

        return $entry;
    }

    /**
     * The stories of a release by their public titles, grouped under the
     * title of their epic; a hidden one is counted and left out.
     *
     * @param  array<string, mixed>  $release
     * @param  array<string, mixed>  $override
     * @param  array<string, array<string, mixed>>  $index
     * @return array{groups: list<array{title: string|null, items: list<array{title: string, summary: string|null}>}>, count: int, hidden: int}
     */
    protected function items(array $release, array $override, array $index): array
    {
        $overrides = is_array($override['items'] ?? null) ? $override['items'] : [];
        $groups = [];
        $loose = [];
        $count = 0;
        $hidden = 0;

        foreach (is_array($release['specs'] ?? null) ? $release['specs'] : [] as $code) {
            $code = strtoupper(trim((string) $code));
            $spec = $index[$code] ?? null;

            if ($spec === null) {
                continue;
            }

            $own = is_array($overrides[$code] ?? null) ? $overrides[$code] : [];

            if (($own['hidden'] ?? false) === true) {
                $hidden++;

                continue;
            }

            $title = $this->text($own['title'] ?? null) ?? $this->publicTitle((string) ($spec['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            $item = ['title' => $title, 'summary' => $this->text($own['summary'] ?? null)];
            $epic = is_array($spec['epic'] ?? null) ? $this->publicTitle((string) ($spec['epic']['title'] ?? '')) : '';
            $count++;

            if ($epic === '') {
                $loose[] = $item;
            } else {
                $groups[$epic][] = $item;
            }
        }

        $result = [];

        foreach ($groups as $title => $items) {
            $result[] = ['title' => (string) $title, 'items' => $items];
        }

        if ($loose !== []) {
            $result[] = ['title' => null, 'items' => $loose];
        }

        return ['groups' => $result, 'count' => $count, 'hidden' => $hidden];
    }

    /**
     * A title with no code in it: `US-001: Login` and `EP-002 — Billing`
     * read as `Login` and `Billing`.
     */
    protected function publicTitle(string $title): string
    {
        $clean = (string) preg_replace('/\b[A-Z][A-Z0-9]*-\d+\b[\s:—–-]*/u', '', trim($title));

        return trim($clean, " \t:—–-");
    }

    /**
     * When a version is due, in the words the outside is given: the curated
     * target as written, else the milestone of the release as a month,
     * else the day a shipped release went out.
     *
     * @param  array<string, mixed>  $override
     * @param  array<string, mixed>  $release
     * @return array{text: string, kind: string}|null
     */
    protected function target(string $status, array $override, array $release, ?string $milestone): ?array
    {
        $curated = $this->text($override['target'] ?? null);

        if ($curated !== null) {
            $day = PlanDate::day($curated);

            return ['text' => $day !== null ? $this->month($day) : $curated, 'kind' => $status === 'shipped' ? 'shipped' : 'target'];
        }

        if ($status === 'shipped') {
            $day = PlanDate::day(is_string($release['shipped_at'] ?? null) ? substr($release['shipped_at'], 0, 10) : null);

            return $day !== null ? ['text' => (new DateTimeImmutable($day))->format('j M Y'), 'kind' => 'shipped'] : null;
        }

        if ($milestone !== null) {
            return ['text' => $this->month($milestone), 'kind' => 'target'];
        }

        return null;
    }

    /**
     * `Oct 2026`: a plan is told by the month, not the day.
     */
    protected function month(string $day): string
    {
        return (new DateTimeImmutable($day))->format('M Y');
    }

    /**
     * The milestone of each release, from the schedule.
     *
     * @return array<string, string>
     */
    protected function milestones(): array
    {
        $dates = [];

        foreach ($this->usage->schedule()['deadlines'] as $deadline) {
            $release = trim((string) ($deadline['release'] ?? ''));
            $day = PlanDate::day($deadline['date'] ?? null);

            if ($release !== '' && $day !== null) {
                $release = ltrim($release, 'vV');
                $dates[$release] = isset($dates[$release]) ? min($dates[$release], $day) : $day;
            }
        }

        return $dates;
    }

    /**
     * @param  array{intro: string|null, public_token: string|null, versions: array<string, array<string, mixed>>, updated_at: string|null}  $curated
     */
    protected function updatedAt(array $curated): string
    {
        $dates = array_filter([
            $curated['updated_at'],
            $this->releases->read()['updated_at'],
        ]);

        $latest = $dates !== [] ? max($dates) : null;

        try {
            return (new DateTimeImmutable($latest ?? 'now'))->format('j M Y');
        } catch (\Exception) {
            return (new DateTimeImmutable('now'))->format('j M Y');
        }
    }

    protected function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
