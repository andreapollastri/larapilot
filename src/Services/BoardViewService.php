<?php

declare(strict_types=1);

namespace Larapilot\Services;

/**
 * How the Board and the Plan are read: grouped by workflow status (the
 * Board), by epic (the Plan), by version, or by sprint — and narrowed to
 * one version or one sprint. The query says what is asked for; this
 * service says what is available and what each spec belongs to, so both
 * pages and the API tell the same story.
 */
class BoardViewService
{
    /**
     * The `**Release:**` line a spec body carries when a skill assigned it.
     */
    private const RELEASE_LINE = '/^[ \t>*-]*\*\*Release:?\*\*:?[ \t]*v?(\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?)/mi';

    public const NO_VERSION = 'none';

    public const DELIVERED = 'none';

    public function __construct(
        protected ConfigService $config,
        protected ReleaseService $releases,
        protected SprintService $sprints,
    ) {}

    /**
     * Whether a version grouping is offered: release mode is on, or the
     * ledger already holds releases.
     */
    public function versionsAvailable(): bool
    {
        return $this->config->releaseModeEnabled() || $this->releases->read()['releases'] !== [];
    }

    /**
     * Whether a sprint grouping is offered.
     */
    public function sprintsAvailable(): bool
    {
        return $this->sprints->enabled();
    }

    /**
     * The view the query asks for, kept to what the project offers.
     *
     * @param  array<string, mixed>  $query  The query string of the request.
     * @param  list<array<string, mixed>>  $specs  The whole backlog, raw.
     * @param  string  $default  The grouping the page opens on: `status` or `epic`.
     * @return array{
     *     group: string,
     *     default: string,
     *     version: string|null,
     *     sprint: int|string|null,
     *     filtered: bool,
     *     options: list<array{key: string, label: string}>,
     *     versions: list<array<string, mixed>>,
     *     sprints: list<array<string, mixed>>,
     *     sprints_enabled: bool,
     *     sprint_plan: array<string, mixed>|null,
     *     assignments: array<string, array{release: string|null, sprint: int|null, sprint_proposed: bool}>
     * }
     */
    public function resolve(array $query, array $specs, string $default = 'status'): array
    {
        $versionsOn = $this->versionsAvailable();
        $sprintsOn = $this->sprintsAvailable();

        $options = [['key' => $default, 'label' => $default === 'epic' ? 'Epic' : 'Status']];

        if ($versionsOn) {
            $options[] = ['key' => 'version', 'label' => 'Version'];
        }

        if ($sprintsOn) {
            $options[] = ['key' => 'sprint', 'label' => 'Sprint'];
        }

        $group = strtolower(trim((string) ($this->scalar($query, 'group') ?? '')));

        if (! in_array($group, array_column($options, 'key'), true)) {
            $group = $default;
        }

        $versions = $versionsOn ? $this->versions($specs) : [];
        $releaseOf = $this->releaseOf($specs, $versions);
        $plan = $this->sprints->plan($specs, $sprintsOn);

        $assignments = [];

        foreach ($specs as $spec) {
            $code = (string) ($spec['code'] ?? '');

            if ($code === '') {
                continue;
            }

            $assignments[$code] = [
                'release' => $releaseOf[$code] ?? null,
                'sprint' => $plan['assignments'][$code]['sprint'] ?? null,
                'sprint_proposed' => $plan['assignments'][$code]['proposed'] ?? false,
            ];
        }

        $version = null;
        $asked = trim((string) ($this->scalar($query, 'version') ?? ''));

        if ($versionsOn && $asked !== '') {
            if (strtolower($asked) === self::NO_VERSION) {
                $version = self::NO_VERSION;
            } else {
                $needle = ltrim($asked, 'vV');

                foreach ($versions as $row) {
                    if ($row['version'] === $needle) {
                        $version = $needle;
                    }
                }
            }
        }

        $sprint = null;
        $askedSprint = trim((string) ($this->scalar($query, 'sprint') ?? ''));

        if ($sprintsOn && $askedSprint !== '') {
            if (strtolower($askedSprint) === self::DELIVERED) {
                $sprint = self::DELIVERED;
            } elseif (preg_match('/^\d+$/', $askedSprint) === 1 && (int) $askedSprint > 0) {
                $sprint = (int) $askedSprint;
            }
        }

        return [
            'group' => $group,
            'default' => $default,
            'version' => $version,
            'sprint' => $sprint,
            'filtered' => $version !== null || $sprint !== null,
            'options' => $options,
            'versions' => $versions,
            'sprints' => $sprintsOn ? $plan['sprints'] : [],
            'sprints_enabled' => $sprintsOn,
            'sprint_plan' => $sprintsOn ? [
                'origin' => $plan['origin'],
                'current' => $plan['current'],
                'capacity' => $plan['capacity'],
                'delivered' => $plan['delivered'],
            ] : null,
            'assignments' => $assignments,
        ];
    }

    /**
     * Whether a spec passes the version and sprint filters of a view.
     *
     * @param  array<string, mixed>  $view
     */
    public function matches(string $code, array $view): bool
    {
        $assignment = $view['assignments'][$code] ?? ['release' => null, 'sprint' => null, 'sprint_proposed' => false];

        if ($view['version'] !== null) {
            $wanted = $view['version'] === self::NO_VERSION ? null : $view['version'];

            if ($assignment['release'] !== $wanted) {
                return false;
            }
        }

        if ($view['sprint'] !== null) {
            $wanted = $view['sprint'] === self::DELIVERED ? null : $view['sprint'];

            if ($assignment['sprint'] !== $wanted) {
                return false;
            }
        }

        return true;
    }

    /**
     * The spec with what the view knows about it: its release, its sprint,
     * and whether that sprint is a proposal.
     *
     * @param  array<string, mixed>  $spec
     * @param  array<string, mixed>  $view
     * @return array<string, mixed>
     */
    public function annotate(array $spec, array $view): array
    {
        $code = (string) ($spec['code'] ?? '');
        $assignment = $view['assignments'][$code] ?? ['release' => null, 'sprint' => null, 'sprint_proposed' => false];

        return array_merge($spec, [
            'release' => $assignment['release'],
            'sprint' => $assignment['sprint'],
            'sprint_proposed' => $assignment['sprint_proposed'],
        ]);
    }

    /**
     * The columns of the Board for a grouping: one per status, per version,
     * or per sprint, each with the specs that fall in it. Every column
     * carries what its header says — a status badge, a shipped or current
     * mark, the dates and the capacity of a sprint.
     *
     * @param  array<string, list<array<string, mixed>>>  $byStatus  The kanban columns, status order kept.
     * @param  array<string, mixed>  $view
     * @return list<array{key: string, kind: string, label: string, sub: string|null, badge: string|null, state: string|null, items: list<array<string, mixed>>}>
     */
    public function columns(array $byStatus, array $view): array
    {
        $all = [];

        foreach ($byStatus as $specs) {
            foreach ($specs as $spec) {
                $all[] = $spec;
            }
        }

        if ($view['group'] === 'version') {
            return $this->versionColumns($all, $view);
        }

        if ($view['group'] === 'sprint') {
            return $this->sprintColumns($all, $view);
        }

        $columns = [];

        foreach ($byStatus as $status => $specs) {
            $columns[] = [
                'key' => (string) $status,
                'kind' => 'status',
                'label' => (string) $status,
                'sub' => null,
                'badge' => $this->statusBadge((string) $status),
                'state' => null,
                'items' => array_values($specs),
            ];
        }

        return $columns;
    }

    /**
     * @param  list<array<string, mixed>>  $specs
     * @param  array<string, mixed>  $view
     * @return list<array{key: string, kind: string, label: string, sub: string|null, badge: string|null, state: string|null, items: list<array<string, mixed>>}>
     */
    protected function versionColumns(array $specs, array $view): array
    {
        $buckets = [];
        $loose = [];

        foreach ($specs as $spec) {
            $release = $view['assignments'][(string) ($spec['code'] ?? '')]['release'] ?? null;

            if ($release === null) {
                $loose[] = $spec;
            } else {
                $buckets[$release][] = $spec;
            }
        }

        $columns = [];

        foreach ($view['versions'] as $row) {
            $columns[] = [
                'key' => 'v'.$row['version'],
                'kind' => 'version',
                'label' => 'v'.$row['version'],
                'sub' => $row['title'],
                'badge' => null,
                'state' => $row['status'],
                'items' => $buckets[$row['version']] ?? [],
            ];
        }

        $columns[] = [
            'key' => 'no-version',
            'kind' => 'version',
            'label' => 'No version yet',
            'sub' => 'Stories no release claims',
            'badge' => null,
            'state' => 'none',
            'items' => $loose,
        ];

        return $columns;
    }

    /**
     * @param  list<array<string, mixed>>  $specs
     * @param  array<string, mixed>  $view
     * @return list<array{key: string, kind: string, label: string, sub: string|null, badge: string|null, state: string|null, items: list<array<string, mixed>>}>
     */
    protected function sprintColumns(array $specs, array $view): array
    {
        $buckets = [];
        $delivered = [];

        foreach ($specs as $spec) {
            $sprint = $view['assignments'][(string) ($spec['code'] ?? '')]['sprint'] ?? null;

            if ($sprint === null) {
                $delivered[] = $spec;
            } else {
                $buckets[$sprint][] = $spec;
            }
        }

        $columns = [];

        foreach ($view['sprints'] as $sprint) {
            $columns[] = [
                'key' => 'sprint-'.$sprint['number'],
                'kind' => 'sprint',
                'label' => 'Sprint '.$sprint['number'],
                'sub' => self::range($sprint['start'], $sprint['end']),
                'badge' => null,
                'state' => $sprint['current'] ? 'current' : ($sprint['past'] ? 'past' : 'planned'),
                'items' => $buckets[$sprint['number']] ?? [],
                'proposed' => $sprint['proposed'],
            ];
        }

        if ($delivered !== []) {
            $columns[] = [
                'key' => 'delivered',
                'kind' => 'sprint',
                'label' => 'Delivered',
                'sub' => 'Done before sprints were planned',
                'badge' => null,
                'state' => 'none',
                'items' => $delivered,
            ];
        }

        return $columns;
    }

    /**
     * The releases of the ledger, oldest version first, with the specs each
     * claims and what it has delivered of them.
     *
     * @param  list<array<string, mixed>>  $specs
     * @return list<array{version: string, title: string, status: string, shipped: bool, current: bool, shipped_at: string|null, specs: list<string>, points: int, done: int}>
     */
    public function versions(array $specs): array
    {
        $done = strtoupper($this->config->status('done'));
        $index = [];

        foreach ($specs as $spec) {
            $index[strtoupper((string) ($spec['code'] ?? ''))] = $spec;
        }

        $rows = [];

        foreach ($this->releases->list() as $release) {
            $version = (string) ($release['version'] ?? '');

            if ($version === '') {
                continue;
            }

            $codes = array_values(array_filter(array_map(
                static fn (mixed $code): string => strtoupper(trim((string) $code)),
                is_array($release['specs'] ?? null) ? $release['specs'] : []
            )));
            $points = 0;
            $delivered = 0;

            foreach ($codes as $code) {
                $spec = $index[$code] ?? null;

                if ($spec === null) {
                    continue;
                }

                $points += max(0, (int) ($spec['points'] ?? 0));
                $delivered += strtoupper((string) ($spec['status'] ?? '')) === $done ? 1 : 0;
            }

            $status = strtolower((string) ($release['status'] ?? 'planned'));

            $rows[] = [
                'version' => $version,
                'title' => trim((string) ($release['title'] ?? '')),
                'status' => $status,
                'shipped' => $status === 'shipped',
                'current' => $status === 'in_progress',
                'shipped_at' => is_string($release['shipped_at'] ?? null) ? $release['shipped_at'] : null,
                'specs' => $codes,
                'points' => $points,
                'done' => $delivered,
            ];
        }

        return $rows;
    }

    /**
     * The release each spec belongs to: what the ledger says first, then
     * the `**Release:**` line of its body when it names a release the
     * ledger knows.
     *
     * @param  list<array<string, mixed>>  $specs
     * @param  list<array<string, mixed>>  $versions
     * @return array<string, string>
     */
    protected function releaseOf(array $specs, array $versions): array
    {
        $map = [];
        $known = [];

        foreach ($versions as $row) {
            $known[$row['version']] = true;

            foreach ($row['specs'] as $code) {
                $map[$code] ??= $row['version'];
            }
        }

        $result = [];

        foreach ($specs as $spec) {
            $code = (string) ($spec['code'] ?? '');

            if ($code === '') {
                continue;
            }

            $release = $map[strtoupper($code)] ?? null;

            if ($release === null && preg_match(self::RELEASE_LINE, (string) ($spec['body'] ?? ''), $match) === 1 && isset($known[$match[1]])) {
                $release = $match[1];
            }

            if ($release !== null) {
                $result[$code] = $release;
            }
        }

        return $result;
    }

    /**
     * The class the Board gives a status badge.
     */
    public function statusBadge(string $status): string
    {
        return match (strtoupper($status)) {
            'TODO' => 'badge-todo',
            'PLANNED' => 'badge-planned',
            'IN PROGRESS' => 'badge-in-progress',
            'REVIEW' => 'badge-review',
            'DONE' => 'badge-done',
            default => 'badge-todo',
        };
    }

    /**
     * "6 – 19 Oct", with the year when it is not this one.
     */
    public static function range(string $from, string $to): string
    {
        try {
            $a = new \DateTimeImmutable($from);
            $b = new \DateTimeImmutable($to);
        } catch (\Exception) {
            return $from.' → '.$to;
        }

        $year = static fn (\DateTimeImmutable $day): string => $day->format('Y') === date('Y') ? '' : ' '.$day->format('Y');

        return match (true) {
            $a->format('Y-m') === $b->format('Y-m') => $a->format('j').' – '.$b->format('j M').$year($b),
            $a->format('Y') === $b->format('Y') => $a->format('j M').' – '.$b->format('j M').$year($b),
            default => $a->format('j M').$year($a).' – '.$b->format('j M').$year($b),
        };
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function scalar(array $query, string $key): ?string
    {
        $value = $query[$key] ?? null;

        return is_scalar($value) ? substr((string) $value, 0, 80) : null;
    }
}
