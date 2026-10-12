<?php

declare(strict_types=1);

namespace Larapilot\Services;

use DateTimeImmutable;
use Larapilot\Support\AtomicFile;
use Larapilot\Support\PlanDate;

/**
 * The versions of the product as one reading: which version each story
 * targets, what each version holds and has delivered, when its last story
 * is forecast to land against its milestone, and its release notes. The
 * Versions page, the version scope of the Board, the Plan and the Forecast,
 * `release-show`, `release-notes`, `release-list`, `spec-next`, the API,
 * and the context of every skill read it from here, so they never tell two
 * stories.
 */
class VersionService
{
    /**
     * The scope of the stories no version claims.
     */
    public const NONE = 'none';

    /**
     * The `**Release:**` line a spec body carries when a skill assigned it.
     */
    private const RELEASE_LINE = '/^[ \t>*-]*\*\*Release:?\*\*:?[ \t]*v?(\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?)/mi';

    /**
     * The `**Type:**` line of a spec body: Feature, Enhancement, Change request, Fix.
     */
    private const TYPE_LINE = '/^[ \t>*-]*\*\*Type:?\*\*:?[ \t]*([A-Za-z][A-Za-z -]*)/mi';

    public function __construct(
        protected ConfigService $config,
        protected ReleaseService $releases,
        protected SpecService $specs,
        protected UsageService $usage,
        protected ReleaseFlowService $flow,
    ) {}

    /**
     * Whether versions are a dimension of this project: release mode is on,
     * or the ledger already holds releases.
     */
    public function enabled(): bool
    {
        return $this->config->releaseModeEnabled() || $this->releases->read()['releases'] !== [];
    }

    /**
     * The version each story targets: what the ledger says first, then the
     * `**Release:**` line of its body when it names a version the ledger
     * knows and that version did not move the story elsewhere.
     *
     * @param  list<array<string, mixed>>|null  $specs
     * @return array<string, string> Spec code → version.
     */
    public function assignments(?array $specs = null): array
    {
        $specs ??= $this->specs->allSpecs();
        $map = [];
        $known = [];
        $deferred = [];

        foreach ($this->releases->list() as $release) {
            $version = (string) ($release['version'] ?? '');

            if ($version === '') {
                continue;
            }

            $known[$version] = true;

            foreach ($this->codes($release['specs'] ?? []) as $code) {
                $map[$code] ??= $version;
            }

            foreach ($this->codes(array_column(is_array($release['deferred'] ?? null) ? $release['deferred'] : [], 'spec')) as $code) {
                $deferred[$version][$code] = true;
            }
        }

        $result = [];

        foreach ($specs as $spec) {
            $code = trim((string) ($spec['code'] ?? ''));

            if ($code === '') {
                continue;
            }

            $upper = strtoupper($code);
            $version = $map[$upper] ?? null;

            if ($version === null
                && preg_match(self::RELEASE_LINE, (string) ($spec['body'] ?? ''), $match) === 1
                && isset($known[$match[1]])
                && ! isset($deferred[$match[1]][$upper])) {
                $version = $match[1];
            }

            if ($version !== null) {
                $result[$code] = $version;
            }
        }

        return $result;
    }

    /**
     * Every version of the ledger, oldest first, with what it holds and
     * has delivered, and — with `$forecast` — the day its last open story
     * is forecast to be done against its milestone.
     *
     * @param  list<array<string, mixed>>|null  $specs
     * @return list<array<string, mixed>>
     */
    public function rows(?array $specs = null, bool $forecast = true): array
    {
        $specs ??= $this->specs->allSpecs();
        $index = $this->index($specs);
        $byVersion = [];

        foreach ($this->assignments($specs) as $code => $version) {
            $byVersion[$version][] = $code;
        }

        $milestones = $forecast ? $this->milestones() : [];
        $ends = $forecast ? $this->forecastEnds() : [];
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $rows = [];

        foreach ($this->releases->list() as $release) {
            $version = (string) ($release['version'] ?? '');

            if ($version === '') {
                continue;
            }

            $codes = $byVersion[$version] ?? [];
            $counts = ['total' => count($codes), 'done' => 0, 'wip' => 0, 'todo' => 0];
            $points = ['total' => 0, 'done' => 0, 'remaining' => 0];
            $end = null;

            foreach ($codes as $code) {
                $spec = $index[strtoupper($code)];
                $state = $this->state($spec);
                $value = max(0, (int) ($spec['points'] ?? 0));
                $counts[$state]++;
                $points['total'] += $value;
                $points[$state === 'done' ? 'done' : 'remaining'] += $value;

                if ($state !== 'done' && isset($ends[strtoupper($code)])) {
                    $end = max($end ?? $ends[strtoupper($code)], $ends[strtoupper($code)]);
                }
            }

            $status = strtolower((string) ($release['status'] ?? 'planned'));
            $shipped = $status === 'shipped';
            $open = $counts['total'] - $counts['done'];
            $milestone = $milestones[$version] ?? null;

            $rows[] = [
                'version' => $version,
                'title' => trim((string) ($release['title'] ?? '')),
                'status' => $status,
                'shipped' => $shipped,
                'current' => $status === 'in_progress',
                'branch' => (string) ($release['branch'] ?? 'release/'.$version),
                'created_at' => is_string($release['created_at'] ?? null) ? $release['created_at'] : null,
                'shipped_at' => is_string($release['shipped_at'] ?? null) ? $release['shipped_at'] : null,
                'specs' => $codes,
                'missing' => array_values(array_filter(
                    $this->codes($release['specs'] ?? []),
                    static fn (string $code): bool => ! isset($index[$code])
                )),
                'deferred' => $this->deferred($release),
                'counts' => $counts,
                'points' => $points,
                'progress' => $this->progress($counts, $points),
                'forecast_end' => $shipped || $open === 0 ? null : $end,
                'milestone' => $milestone,
                'risk' => $this->risk($shipped, $open, $end, $milestone, $today),
            ];
        }

        return $rows;
    }

    /**
     * One version with its stories, its epics, and its release notes.
     *
     * @return array<string, mixed>|null Null when the ledger has no such version.
     */
    public function show(string $version): ?array
    {
        $version = $this->releases->normalizeVersion($version);
        $specs = $this->specs->allSpecs();
        $row = null;

        foreach ($this->rows($specs) as $candidate) {
            if ($candidate['version'] === $version) {
                $row = $candidate;
            }
        }

        if ($row === null) {
            return null;
        }

        $index = $this->index($specs);
        $stories = [];
        $epics = [];

        foreach ($row['specs'] as $code) {
            $spec = $index[strtoupper($code)];
            $state = $this->state($spec);
            $epic = is_array($spec['epic'] ?? null) ? $spec['epic'] : [];
            $epicCode = trim((string) ($epic['code'] ?? ''));
            $value = max(0, (int) ($spec['points'] ?? 0));

            $stories[] = [
                'code' => $code,
                'title' => (string) ($spec['title'] ?? $code),
                'status' => (string) ($spec['status'] ?? ''),
                'state' => $state,
                'type' => $this->type($spec),
                'priority' => (string) ($spec['priority'] ?? ''),
                'points' => $value,
                'epic' => $epicCode !== '' ? $epicCode : null,
            ];

            $key = $epicCode !== '' ? $epicCode : '';
            $epics[$key] ??= ['code' => $epicCode !== '' ? $epicCode : null, 'title' => $epicCode !== '' ? trim((string) ($epic['title'] ?? $epicCode)) : 'No epic', 'specs' => 0, 'done' => 0, 'points' => 0];
            $epics[$key]['specs']++;
            $epics[$key]['points'] += $value;
            $epics[$key]['done'] += $state === 'done' ? 1 : 0;
        }

        // Open work first, in the order it is picked up; what is delivered closes the list.
        $rank = ['wip' => 0, 'todo' => 1, 'done' => 2];
        usort($stories, static fn (array $a, array $b): int => [$rank[$a['state']], $a['code']] <=> [$rank[$b['state']], $b['code']]);
        uksort($epics, static fn (string $a, string $b): int => match (true) {
            $a === '' => 1,
            $b === '' => -1,
            default => strnatcasecmp($a, $b),
        });

        return $row + [
            'stories' => $stories,
            'epics' => array_values($epics),
            'notes' => $this->notes($version, $row, $stories),
            'notes_path' => $this->notesPath($version),
            'notes_written' => is_file($this->notesPath($version)),
        ];
    }

    /**
     * The open stories no version claims.
     *
     * @param  list<array<string, mixed>>|null  $specs
     * @return array{count: int, points: int, specs: list<string>}
     */
    public function unassigned(?array $specs = null): array
    {
        $specs ??= $this->specs->allSpecs();
        $assigned = $this->assignments($specs);
        $codes = [];
        $points = 0;

        foreach ($specs as $spec) {
            $code = trim((string) ($spec['code'] ?? ''));

            if ($code === '' || isset($assigned[$code]) || $this->state($spec) === 'done') {
                continue;
            }

            $codes[] = $code;
            $points += max(0, (int) ($spec['points'] ?? 0));
        }

        sort($codes, SORT_NATURAL);

        return ['count' => count($codes), 'points' => $points, 'specs' => $codes];
    }

    /**
     * The version the work is on now: the release branch checked out, the
     * one release in progress, else the oldest in progress, else the
     * oldest planned. Null when nothing is open.
     *
     * @return array{version: string, why: string}|null
     */
    public function current(): ?array
    {
        $git = $this->flow->context();

        if ($git['active_release'] !== null) {
            return ['version' => $git['active_release'], 'why' => $git['current_branch'] !== null && str_starts_with((string) $git['current_branch'], 'release/') ? 'branch' : 'only_in_progress'];
        }

        foreach (['in_progress' => 'oldest_in_progress', 'planned' => 'oldest_planned'] as $status => $why) {
            $open = $this->releases->list($status);

            if ($open !== []) {
                return ['version' => (string) $open[0]['version'], 'why' => $why];
            }
        }

        return null;
    }

    /**
     * What a skill needs to know about versions before it starts: the
     * version the work is on, each open version with its progress, and how
     * many open stories no version claims.
     *
     * @return array<string, mixed>
     */
    public function brief(): array
    {
        $specs = $this->specs->allSpecs();
        $open = [];

        foreach ($this->rows($specs, false) as $row) {
            if ($row['shipped']) {
                continue;
            }

            $open[] = [
                'version' => $row['version'],
                'title' => $row['title'],
                'status' => $row['status'],
                'specs' => $row['counts']['total'],
                'done' => $row['counts']['done'],
                'points' => $row['points']['total'],
                'points_done' => $row['points']['done'],
                'progress' => $row['progress'],
            ];
        }

        $shipped = $this->releases->list('shipped');
        $current = $this->current();

        return [
            'current' => $current['version'] ?? null,
            'current_why' => $current['why'] ?? null,
            'last_shipped' => $shipped !== [] ? (string) end($shipped)['version'] : null,
            'open' => $open,
            'unassigned' => $this->unassigned($specs)['count'],
        ];
    }

    /**
     * The order in which open versions are worked: the ones in progress,
     * oldest first, then the planned ones, then the stories with no
     * version. A lower rank is picked up first.
     *
     * `$first` puts one version ahead of them all: the one checked out.
     *
     * @param  list<array<string, mixed>>|null  $specs
     * @return array<string, int> Spec code → rank.
     */
    public function ranks(?array $specs = null, ?string $first = null): array
    {
        $specs ??= $this->specs->allSpecs();
        $order = $first !== null ? [$first => 0] : [];

        foreach (['in_progress', 'planned', 'shipped'] as $status) {
            foreach ($this->releases->list($status) as $release) {
                $order[(string) $release['version']] ??= count($order);
            }
        }

        $ranks = [];

        foreach ($this->assignments($specs) as $code => $version) {
            $ranks[$code] = $order[$version] ?? count($order);
        }

        // The stories no version claims come after every version.
        foreach ($specs as $spec) {
            $code = (string) ($spec['code'] ?? '');

            if ($code !== '' && ! isset($ranks[$code])) {
                $ranks[$code] = count($order) + 1;
            }
        }

        return $ranks;
    }

    /**
     * The versions a query asks for: one, several separated by commas (or
     * as an array), `none` for the stories no version claims. Versions the
     * ledger does not know are dropped.
     *
     * @return list<string>
     */
    public function selection(mixed $asked): array
    {
        if (is_string($asked)) {
            $asked = explode(',', substr($asked, 0, 400));
        }

        if (! is_array($asked)) {
            return [];
        }

        $known = [];

        foreach ($this->releases->list() as $release) {
            $known[(string) ($release['version'] ?? '')] = true;
        }

        $selected = [];

        foreach ($asked as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $value = ltrim(trim((string) $value), 'vV');

            if (strtolower($value) === self::NONE) {
                $selected[self::NONE] = true;
            } elseif ($value !== '' && isset($known[$value])) {
                $selected[$value] = true;
            }
        }

        return array_keys($selected);
    }

    /**
     * The stories of a version still open: what keeps it from shipping.
     *
     * @return list<array{code: string, title: string, status: string}>
     */
    public function openStories(string $version): array
    {
        $version = $this->releases->normalizeVersion($version);
        $specs = $this->specs->allSpecs();
        $index = $this->index($specs);
        $open = [];

        foreach ($this->assignments($specs) as $code => $assigned) {
            $spec = $index[strtoupper($code)];

            if ($assigned === $version && $this->state($spec) !== 'done') {
                $open[] = ['code' => $code, 'title' => (string) ($spec['title'] ?? $code), 'status' => (string) ($spec['status'] ?? '')];
            }
        }

        usort($open, static fn (array $a, array $b): int => strnatcmp($a['code'], $b['code']));

        return $open;
    }

    /**
     * The release notes of a version, drafted from its stories: what was
     * added, changed, and fixed, what is not delivered yet, and what moved
     * to another version. The release skill rewrites the draft in the PRD
     * language; the Versions page and `release-notes` show it as it is.
     *
     * @param  array<string, mixed>|null  $row
     * @param  list<array<string, mixed>>|null  $stories
     */
    public function notes(string $version, ?array $row = null, ?array $stories = null): string
    {
        if ($row === null || $stories === null) {
            $shown = $this->show($version);

            if ($shown === null) {
                throw new \InvalidArgumentException("Release {$version} not found.");
            }

            return $shown['notes'];
        }

        $lines = ['# v'.$row['version'].($row['title'] !== '' ? ' — '.$row['title'] : ''), ''];

        if ($row['shipped']) {
            $lines[] = 'Released'.($row['shipped_at'] !== null ? ' on '.substr($row['shipped_at'], 0, 10) : '').'.';
        } else {
            $lines[] = ucfirst(str_replace('_', ' ', $row['status'])).': '.$row['counts']['done'].' of '.$row['counts']['total'].' stories delivered'
                .($row['forecast_end'] !== null ? ', the last one forecast for '.$row['forecast_end'] : '').'.';
        }

        $sections = ['Added' => [], 'Changed' => [], 'Fixed' => []];
        $pending = [];

        foreach ($stories as $story) {
            $line = '- '.$story['title'].' ('.$story['code'].')';

            if ($story['state'] !== 'done') {
                $pending[] = $line.' — '.$story['status'];

                continue;
            }

            $sections[match ($story['type']) {
                'fix' => 'Fixed',
                'enhancement', 'change' => 'Changed',
                default => 'Added',
            }][] = $line;
        }

        foreach ($sections as $heading => $items) {
            if ($items !== []) {
                $lines = [...$lines, '', '## '.$heading, '', ...$items];
            }
        }

        if ($pending !== []) {
            $lines = [...$lines, '', '## Not delivered yet', '', ...$pending];
        }

        if ($row['deferred'] !== []) {
            $moved = array_map(
                static fn (array $item): string => '- '.$item['spec'].($item['to'] !== null ? ' → v'.$item['to'] : ' → backlog'),
                $row['deferred']
            );
            $lines = [...$lines, '', '## Moved to a later version', '', ...$moved];
        }

        if (array_sum(array_map('count', $sections)) === 0 && $pending === []) {
            $lines = [...$lines, '', 'No story targets this version yet.'];
        }

        return implode("\n", $lines)."\n";
    }

    public function notesPath(string $version): string
    {
        $config = $this->config->resolve();
        $directory = $this->config->absolutePath($config['paths']['release_notes'] ?? '.larapilot/docs/releases/');

        return rtrim($directory, '/').'/v'.$this->releases->normalizeVersion($version).'.md';
    }

    /**
     * Write the release notes of a version where the team keeps them.
     */
    public function writeNotes(string $version, ?string $markdown = null): string
    {
        $markdown ??= $this->notes($version);
        $path = $this->notesPath($version);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        AtomicFile::write($path, rtrim($markdown)."\n");

        return $path;
    }

    /**
     * The state of a spec as the versions count it.
     *
     * @param  array<string, mixed>  $spec
     * @return 'done'|'wip'|'todo'
     */
    public function state(array $spec): string
    {
        $status = strtoupper(trim((string) ($spec['status'] ?? '')));

        return match (true) {
            $status === strtoupper($this->config->status('done')) => 'done',
            in_array($status, [strtoupper($this->config->status('in_progress')), strtoupper($this->config->status('review'))], true) => 'wip',
            default => 'todo',
        };
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return 'feature'|'enhancement'|'change'|'fix'|null
     */
    protected function type(array $spec): ?string
    {
        if (preg_match(self::TYPE_LINE, (string) ($spec['body'] ?? ''), $match) !== 1) {
            return null;
        }

        $type = strtolower(trim($match[1]));

        return match (true) {
            str_starts_with($type, 'fix'), str_starts_with($type, 'bug') => 'fix',
            str_starts_with($type, 'enhancement') => 'enhancement',
            str_starts_with($type, 'change') => 'change',
            str_starts_with($type, 'feature') => 'feature',
            default => null,
        };
    }

    /**
     * The earliest milestone tied to each version.
     *
     * @return array<string, array{label: string, date: string, status: string}>
     */
    protected function milestones(): array
    {
        $milestones = [];

        foreach ($this->usage->schedule()['deadlines'] ?? [] as $deadline) {
            if (! is_array($deadline)) {
                continue;
            }

            $version = ltrim(trim((string) ($deadline['release'] ?? '')), 'vV');
            $date = PlanDate::day($deadline['date'] ?? null);

            if ($version === '' || $date === null) {
                continue;
            }

            if (! isset($milestones[$version]) || $date < $milestones[$version]['date']) {
                $milestones[$version] = [
                    'label' => trim((string) ($deadline['label'] ?? '')),
                    'date' => $date,
                    'status' => $this->usage->deadlineStatus($deadline),
                ];
            }
        }

        return $milestones;
    }

    /**
     * The forecast end of every open spec the delivery chart draws.
     *
     * @return array<string, string> Upper-case spec code → day.
     */
    protected function forecastEnds(): array
    {
        $ends = [];

        foreach ($this->usage->gantt()['bars'] ?? [] as $bar) {
            if (($bar['type'] ?? '') === 'epic' || ! is_string($bar['end'] ?? null)) {
                continue;
            }

            $code = strtoupper(explode('·', (string) ($bar['id'] ?? ''), 2)[0]);
            $ends[$code] = max($ends[$code] ?? $bar['end'], $bar['end']);
        }

        return $ends;
    }

    /**
     * @param  array{label: string, date: string, status: string}|null  $milestone
     * @return 'late'|'overdue'|'on_track'|null
     */
    protected function risk(bool $shipped, int $open, ?string $end, ?array $milestone, string $today): ?string
    {
        if ($shipped || $milestone === null || $milestone['status'] === 'done') {
            return null;
        }

        if ($open > 0 && $milestone['date'] < $today) {
            return 'overdue';
        }

        if ($end !== null && $end > $milestone['date']) {
            return 'late';
        }

        return 'on_track';
    }

    /**
     * @param  array{total: int, done: int, wip: int, todo: int}  $counts
     * @param  array{total: int, done: int, remaining: int}  $points
     */
    protected function progress(array $counts, array $points): int
    {
        if ($points['total'] > 0) {
            return (int) floor($points['done'] * 100 / $points['total']);
        }

        return $counts['total'] > 0 ? (int) floor($counts['done'] * 100 / $counts['total']) : 0;
    }

    /**
     * @param  array<string, mixed>  $release
     * @return list<array{spec: string, to: string|null, at: string|null}>
     */
    protected function deferred(array $release): array
    {
        $items = [];

        foreach (is_array($release['deferred'] ?? null) ? $release['deferred'] : [] as $item) {
            if (! is_array($item) || trim((string) ($item['spec'] ?? '')) === '') {
                continue;
            }

            $items[] = [
                'spec' => strtoupper(trim((string) $item['spec'])),
                'to' => is_string($item['to'] ?? null) && $item['to'] !== '' ? $item['to'] : null,
                'at' => is_string($item['at'] ?? null) ? $item['at'] : null,
            ];
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $specs
     * @return array<string, array<string, mixed>> Upper-case code → spec.
     */
    protected function index(array $specs): array
    {
        $index = [];

        foreach ($specs as $spec) {
            $code = strtoupper(trim((string) ($spec['code'] ?? '')));

            if ($code !== '') {
                $index[$code] = $spec;
            }
        }

        return $index;
    }

    /**
     * @return list<string>
     */
    protected function codes(mixed $codes): array
    {
        if (! is_array($codes)) {
            return [];
        }

        $result = [];

        foreach ($codes as $code) {
            if (is_string($code) || is_numeric($code)) {
                $code = strtoupper(trim((string) $code));

                if ($code !== '') {
                    $result[] = $code;
                }
            }
        }

        return array_values(array_unique($result));
    }
}
