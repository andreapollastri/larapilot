<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Composer\Semver\Comparator;
use Composer\Semver\VersionParser;
use Larapilot\Services\Sbom\OsvClient;
use Larapilot\Services\Sbom\VendorAuditLedger;
use Larapilot\Support\AtomicFile;
use Larapilot\Support\Cvss;

/**
 * Known vulnerabilities in the dependencies the SBOM lists — Composer and
 * JavaScript, the frontend companion included — checked against OSV.dev.
 *
 * Each finding is one advisory on one installed package: its severity (the
 * advisory's own word, else the CVSS 3 score), its CVE aliases, the version
 * that fixes it, and what was decided about it. The result is cached until
 * the next check; decisions live in the committed ledger.
 */
class VendorAuditService
{
    public const SEVERITIES = ['critical', 'high', 'medium', 'low', 'unknown'];

    public const SOURCE = 'OSV.dev';

    public function __construct(
        protected ConfigService $config,
        protected SbomService $sbom,
        protected OsvClient $osv,
        protected VendorAuditLedger $ledger,
    ) {}

    /**
     * Ask OSV.dev again and keep the answer.
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException
     */
    public function check(): array
    {
        $inventory = $this->sbom->inventory(true);
        $queries = [];
        $components = [];

        foreach ($inventory['components'] as $component) {
            if (preg_match('/^\d/', (string) $component['version']) !== 1) {
                // A branch (`dev-main`) is no release an advisory can name.
                continue;
            }

            $key = $component['ecosystem'].'|'.strtolower($component['name']).'|'.$component['version'];

            if (isset($components[$key])) {
                $components[$key]['inventories'][] = $component['inventory'];

                continue;
            }

            $components[$key] = $component + ['inventories' => [$component['inventory']]];
            $queries[] = [
                'ecosystem' => $component['ecosystem'] === 'composer' ? 'Packagist' : 'npm',
                'name' => $component['name'],
                'version' => (string) $component['version'],
            ];
        }

        $answers = $queries === [] ? [] : $this->osv->query($queries);
        $keys = array_keys($components);
        $wanted = [];

        foreach ($answers as $index => $vulns) {
            foreach ($vulns as $vuln) {
                $wanted[$vuln['id']] = $vuln['modified'];
            }
        }

        $advisories = $wanted === [] ? [] : $this->osv->vulnerabilities($wanted);
        $findings = [];

        foreach ($answers as $index => $vulns) {
            $component = $components[$keys[$index]] ?? null;

            if ($component === null) {
                continue;
            }

            foreach ($vulns as $vuln) {
                $advisory = $advisories[$vuln['id']] ?? ['id' => $vuln['id']];
                $findings[] = $this->finding($component, $advisory);
            }
        }

        $result = [
            'checked_at' => now()->toIso8601String(),
            'source' => self::SOURCE,
            'fingerprint' => $inventory['fingerprint'],
            'components' => count($components),
            'findings' => $findings,
        ];

        $this->store($result);

        $summary = $this->summarize($result);
        $this->ledger->remember([
            'checked_at' => $result['checked_at'],
            'components' => $result['components'],
            'critical' => $summary['counts']['critical'],
            'high' => $summary['counts']['high'],
            'medium' => $summary['counts']['medium'],
            'low' => $summary['counts']['low'],
            'unknown' => $summary['counts']['unknown'],
        ]);

        return $summary;
    }

    /**
     * The last check with today's decisions, or null when none was made.
     *
     * @return array<string, mixed>|null
     */
    public function latest(): ?array
    {
        $path = $this->cachePath();

        if (! is_file($path)) {
            return null;
        }

        $result = json_decode((string) file_get_contents($path), true);

        if (! is_array($result) || ! is_array($result['findings'] ?? null)) {
            return null;
        }

        return $this->summarize($result);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function summarize(array $result): array
    {
        $decisions = $this->ledger->decisions();
        $counts = array_fill_keys(self::SEVERITIES, 0);
        $states = ['open' => array_fill_keys(self::SEVERITIES, 0), 'waived' => array_fill_keys(self::SEVERITIES, 0), 'in_backlog' => array_fill_keys(self::SEVERITIES, 0)];
        $findings = [];
        $packages = [];

        foreach ($result['findings'] as $finding) {
            $decision = $decisions[$finding['id']] ?? null;
            // The ledger is edited by hand: a state it does not know is an open one.
            $state = is_array($decision) ? (string) ($decision['state'] ?? 'open') : 'open';
            $finding['state'] = in_array($state, ['open', VendorAuditLedger::IN_BACKLOG, VendorAuditLedger::WAIVED], true) ? $state : 'open';
            $finding['reason'] = is_array($decision) ? ($decision['reason'] ?? null) : null;
            $finding['spec'] = is_array($decision) ? ($decision['spec'] ?? null) : null;
            $severity = in_array($finding['severity'], self::SEVERITIES, true) ? $finding['severity'] : 'unknown';

            $states[$finding['state']][$severity] = ($states[$finding['state']][$severity] ?? 0) + 1;

            // A waived finding is no longer open; one in the backlog still is.
            if ($finding['state'] !== VendorAuditLedger::WAIVED) {
                $counts[$severity]++;
            }

            $findings[] = $finding;

            $key = $finding['ecosystem'].'|'.strtolower($finding['package']).'|'.$finding['version'];
            $packages[$key] ??= [
                'package' => $finding['package'],
                'ecosystem' => $finding['ecosystem'],
                'version' => $finding['version'],
                'direct' => $finding['direct'],
                'constraint' => $finding['constraint'] ?? null,
                'scope' => $finding['scope'],
                'inventories' => $finding['inventories'],
                'purl' => $finding['purl'],
                'severity' => 'unknown',
                'ids' => [],
                'open' => 0,
                'fixed' => null,
                'fix' => null,
            ];
            $packages[$key]['ids'][] = $finding['id'];
            $packages[$key]['open'] += $finding['state'] === 'open' ? 1 : 0;
            $packages[$key]['severity'] = $this->worst($packages[$key]['severity'], $severity);

            if ($finding['fixed'] !== null && ($packages[$key]['fixed'] === null || $this->compare($finding['fixed'], $packages[$key]['fixed']) > 0)) {
                $packages[$key]['fixed'] = $finding['fixed'];
            }
        }

        $rank = array_flip(self::SEVERITIES);
        usort($findings, static fn (array $a, array $b): int => [$rank[$a['severity']] ?? 9, $a['package'], $a['id']] <=> [$rank[$b['severity']] ?? 9, $b['package'], $b['id']]);

        $packages = array_values($packages);
        $managers = $this->managers();

        foreach ($packages as $index => $package) {
            $packages[$index]['fix'] = $this->fixCommand($package, $managers[$package['inventories'][0] ?? 'assets'] ?? 'npm');
        }

        usort($packages, static fn (array $a, array $b): int => [$rank[$a['severity']] ?? 9, $a['package']] <=> [$rank[$b['severity']] ?? 9, $b['package']]);

        $fingerprint = $this->currentFingerprint();

        return [
            'checked_at' => $result['checked_at'],
            'source' => $result['source'] ?? self::SOURCE,
            'components' => (int) ($result['components'] ?? 0),
            'stale' => $fingerprint !== null && ($result['fingerprint'] ?? null) !== $fingerprint,
            'counts' => $counts,
            'states' => $states,
            'total' => count($findings),
            'open' => array_sum($states['open']),
            'findings' => $findings,
            'packages' => $packages,
            'history' => $this->ledger->history(),
            'ledger' => $this->ledger->relativePath(),
        ];
    }

    /**
     * Whether the findings stop a release: an open or backlog finding at or
     * above the threshold that was not waived.
     *
     * @param  array<string, mixed>  $summary
     * @return array{verdict: string, fail_on: string, blocking: list<string>, summary: string}
     */
    public function gate(array $summary, string $failOn = 'high'): array
    {
        $rank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3, 'unknown' => 4];
        $threshold = $rank[$failOn] ?? 1;
        $blocking = [];
        $warning = 0;
        $unrated = 0;

        foreach ($summary['findings'] as $finding) {
            if ($finding['state'] === VendorAuditLedger::WAIVED) {
                continue;
            }

            $level = $rank[$finding['severity']] ?? 4;

            if ($failOn !== 'none' && $level <= $threshold && $finding['severity'] !== 'unknown') {
                $blocking[] = $finding['id'];
            } else {
                $warning++;
                $unrated += $finding['severity'] === 'unknown' ? 1 : 0;
            }
        }

        $verdict = $blocking !== [] ? 'FAIL' : ($warning > 0 ? 'WARN' : 'PASS');

        return [
            'verdict' => $verdict,
            'fail_on' => $failOn,
            'blocking' => array_values(array_unique($blocking)),
            'summary' => match ($verdict) {
                'FAIL' => count(array_unique($blocking)).' open vulnerabilit'.(count(array_unique($blocking)) === 1 ? 'y' : 'ies').' at '.$failOn.' or above.',
                // An unrated advisory is not below the threshold: it has no rating. Say so.
                'WARN' => $warning.' open vulnerabilit'.($warning === 1 ? 'y' : 'ies').' below '.$failOn.($unrated > 0 ? ' ('.$unrated.' unrated: read the advisor'.($unrated === 1 ? 'y' : 'ies').')' : '').'.',
                default => 'No open vulnerability in the dependencies.',
            },
        ];
    }

    /**
     * Record a decision for advisory ids of the last check.
     *
     * @param  list<string>  $ids
     * @return list<string> the ids recorded
     *
     * @throws \InvalidArgumentException
     */
    public function decide(array $ids, ?string $spec, ?string $reason, bool $clear = false): array
    {
        $ids = array_values(array_unique(array_filter(array_map('trim', $ids))));

        if ($ids === []) {
            throw new \InvalidArgumentException('Name at least one advisory id, e.g. GHSA-wxmh-65f7-jcvw.');
        }

        $known = array_column($this->latest()['findings'] ?? [], 'id');
        $unknown = array_values(array_diff($ids, $known));

        if (! $clear && $unknown !== []) {
            throw new \InvalidArgumentException('Not in the last check: '.implode(', ', $unknown).'. Run php artisan larapilot:vendor-audit first.');
        }

        if ($clear) {
            $this->ledger->forget($ids);

            return $ids;
        }

        if ($spec !== null) {
            $this->ledger->record($ids, ['state' => VendorAuditLedger::IN_BACKLOG, 'spec' => strtoupper($spec)]);
        } else {
            if ($reason === null || trim($reason) === '') {
                throw new \InvalidArgumentException('A waiver needs a reason: --reason="…".');
            }

            $this->ledger->record($ids, ['state' => VendorAuditLedger::WAIVED, 'reason' => trim($reason)]);
        }

        return $ids;
    }

    /**
     * The report for the team, under `paths.security`.
     *
     * @param  array<string, mixed>  $summary
     * @return array{path: string, relative: string}
     */
    public function writeReport(array $summary): array
    {
        $directory = rtrim((string) $this->config->setupInfo()['paths']['security'], '/');
        $path = $directory.'/vendor-audit.md';

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        AtomicFile::write($path, $this->report($summary));

        return ['path' => $path, 'relative' => $this->config->relativePath($path)];
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function report(array $summary): string
    {
        $gate = $this->gate($summary);
        $lines = ['# Vulnerable dependencies', ''];
        $lines[] = 'Checked against '.$summary['source'].' on '.substr((string) $summary['checked_at'], 0, 16).': '.$summary['components'].' packages, '.$summary['total'].' advisories, gate **'.$gate['verdict'].'** — '.$gate['summary'];

        if ($summary['stale']) {
            $lines[] = '';
            $lines[] = '> The lockfiles changed after this check: run `php artisan larapilot:vendor-audit` again.';
        }

        $lines[] = '';
        $lines[] = '## By package';
        $lines[] = '';

        if ($summary['packages'] === []) {
            $lines[] = '_No known vulnerability._';
        } else {
            $lines[] = '| Package | Version | Severity | Advisories | Fixed in | Fix |';
            $lines[] = '| --- | --- | --- | --- | --- | --- |';

            foreach ($summary['packages'] as $package) {
                $lines[] = '| '.$package['package'].' ('.$package['ecosystem'].($package['direct'] ? ', direct' : '').') | '.$package['version'].' | '.ucfirst($package['severity']).' | '.implode(', ', $package['ids']).' | '.($package['fixed'] ?? '—').' | `'.$package['fix'].'` |';
            }
        }

        foreach ($summary['findings'] as $finding) {
            $lines[] = '';
            $lines[] = '## '.$finding['id'].' — '.$finding['package'].' '.$finding['version'];
            $lines[] = '';
            $lines[] = '- Severity: '.ucfirst($finding['severity']).($finding['score'] !== null ? ' (CVSS '.$finding['score'].')' : '');

            if ($finding['aliases'] !== []) {
                $lines[] = '- Also known as: '.implode(', ', $finding['aliases']);
            }

            $lines[] = '- Fixed in: '.($finding['fixed'] ?? 'no fixed version published');
            $lines[] = '- Decision: '.match ($finding['state']) {
                'waived' => 'waived — '.$finding['reason'],
                'in_backlog' => 'in the backlog as '.$finding['spec'],
                default => 'none yet',
            };
            $lines[] = '- Advisory: '.$finding['url'];
            $lines[] = '';
            $lines[] = (string) $finding['summary'];
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<string, mixed>  $component
     * @param  array<string, mixed>  $advisory
     * @return array<string, mixed>
     */
    protected function finding(array $component, array $advisory): array
    {
        [$severity, $score] = $this->severity($advisory);
        $aliases = array_values(array_filter(
            array_map('strval', is_array($advisory['aliases'] ?? null) ? $advisory['aliases'] : []),
            static fn (string $alias): bool => $alias !== ''
        ));

        $summary = trim((string) ($advisory['summary'] ?? ''));

        if ($summary === '') {
            $details = trim((string) ($advisory['details'] ?? ''));
            $summary = $details !== '' ? mb_substr((string) preg_replace('/\s+/', ' ', strip_tags($details)), 0, 220) : 'No summary published.';
        }

        $id = (string) ($advisory['id'] ?? '');

        return [
            'id' => $id,
            'package' => (string) $component['name'],
            'ecosystem' => (string) $component['ecosystem'],
            'version' => (string) $component['version'],
            'direct' => (bool) $component['direct'],
            'constraint' => $component['constraint'] ?? null,
            'scope' => $component['scope'],
            'inventories' => array_values(array_unique($component['inventories'])),
            'purl' => (string) $component['purl'],
            'severity' => $severity,
            'score' => $score,
            'aliases' => $aliases,
            'summary' => $summary,
            'published' => is_string($advisory['published'] ?? null) ? $advisory['published'] : null,
            'fixed' => $this->fixedVersion($advisory, (string) $component['name'], (string) $component['version']),
            'url' => 'https://osv.dev/vulnerability/'.rawurlencode($id),
        ];
    }

    /**
     * The advisory's own word (GitHub: LOW, MODERATE, HIGH, CRITICAL), else
     * the band of its CVSS 3 base score.
     *
     * @param  array<string, mixed>  $advisory
     * @return array{0: string, 1: float|null}
     */
    protected function severity(array $advisory): array
    {
        $score = null;

        foreach (is_array($advisory['severity'] ?? null) ? $advisory['severity'] : [] as $entry) {
            if (is_array($entry) && is_string($entry['score'] ?? null)) {
                $score ??= Cvss::score($entry['score']);
            }
        }

        $word = strtoupper((string) ($advisory['database_specific']['severity'] ?? ''));

        $severity = match ($word) {
            'CRITICAL' => 'critical',
            'HIGH' => 'high',
            'MODERATE', 'MEDIUM' => 'medium',
            'LOW' => 'low',
            default => $score !== null && $score > 0 ? Cvss::severity($score) : 'unknown',
        };

        return [$severity, $score];
    }

    /**
     * The smallest version above the installed one that the advisory says
     * fixes it, for this package.
     *
     * @param  array<string, mixed>  $advisory
     */
    protected function fixedVersion(array $advisory, string $name, string $installed): ?string
    {
        $fixes = [];

        foreach (is_array($advisory['affected'] ?? null) ? $advisory['affected'] : [] as $affected) {
            if (! is_array($affected) || strtolower((string) ($affected['package']['name'] ?? '')) !== strtolower($name)) {
                continue;
            }

            foreach (is_array($affected['ranges'] ?? null) ? $affected['ranges'] : [] as $range) {
                foreach (is_array($range['events'] ?? null) ? $range['events'] : [] as $event) {
                    if (is_array($event) && is_string($event['fixed'] ?? null) && $this->compare($event['fixed'], $installed) > 0) {
                        $fixes[] = $event['fixed'];
                    }
                }
            }
        }

        if ($fixes === []) {
            return null;
        }

        usort($fixes, fn (string $a, string $b): int => $this->compare($a, $b));

        return $fixes[0];
    }

    /**
     * The command that moves a package to its fixed version.
     *
     * @param  array<string, mixed>  $package
     */
    protected function fixCommand(array $package, string $manager): string
    {
        $name = (string) $package['package'];
        $fixed = $package['fixed'];
        // The constraint already allows the fix: an update moves the lock, the manifest stays.
        $within = $fixed !== null && is_string($package['constraint'] ?? null) && $this->allows($package['constraint'], $fixed);

        if ($package['ecosystem'] === 'composer') {
            if (! $package['direct'] || $within || $fixed === null) {
                return 'composer update '.$name.' --with-dependencies';
            }

            return 'composer require '.($package['scope'] === 'dev' ? '--dev ' : '').$name.':^'.$fixed.' --with-dependencies';
        }

        $target = $fixed !== null ? $name.'@^'.$fixed : $name.'@latest';

        if (! $package['direct'] || $within) {
            return match ($manager) {
                'pnpm' => 'pnpm update '.$name.' --depth Infinity',
                'yarn' => 'yarn up -R '.$name,
                // Yarn 1 has no `up`.
                'yarn-classic' => 'yarn upgrade '.$name,
                'bun' => 'bun update '.$name,
                default => 'npm update '.$name,
            };
        }

        return match ($manager) {
            'pnpm' => 'pnpm add '.$target,
            'yarn', 'yarn-classic' => 'yarn add '.$target,
            'bun' => 'bun add '.$target,
            default => 'npm install '.$target,
        };
    }

    /**
     * The package manager of each inventory.
     *
     * @return array<string, string>
     */
    protected function managers(): array
    {
        $managers = [];

        try {
            foreach ($this->sbom->inventory()['inventories'] as $item) {
                $manager = is_string($item['manager'] ?? null) ? $item['manager'] : 'npm';

                if ($manager === 'yarn' && is_string($item['path'] ?? null) && is_file($item['path'])
                    && str_contains((string) file_get_contents($item['path'], false, null, 0, 400), '# yarn lockfile v1')) {
                    $manager = 'yarn-classic';
                }

                $managers[(string) $item['id']] = $manager;
            }
        } catch (\Throwable) {
            // the commands fall back to npm
        }

        return $managers;
    }

    protected function allows(string $constraint, string $version): bool
    {
        try {
            $parser = new VersionParser;

            return $parser->parseConstraints($constraint)->matches($parser->parseConstraints($parser->normalize(ltrim($version, 'v'))));
        } catch (\Throwable) {
            return false;
        }
    }

    protected function compare(string $a, string $b): int
    {
        try {
            $parser = new VersionParser;
            $left = $parser->normalize(ltrim($a, 'v'));
            $right = $parser->normalize(ltrim($b, 'v'));

            return Comparator::greaterThan($left, $right) ? 1 : (Comparator::lessThan($left, $right) ? -1 : 0);
        } catch (\Throwable) {
            return version_compare(ltrim($a, 'v'), ltrim($b, 'v'));
        }
    }

    protected function worst(string $a, string $b): string
    {
        $rank = array_flip(self::SEVERITIES);

        // `unknown` is the least informative, not the most severe.
        return ($rank[$a] ?? 9) <= ($rank[$b] ?? 9) ? $a : $b;
    }

    protected function currentFingerprint(): ?string
    {
        try {
            return $this->sbom->fingerprint();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function cachePath(): string
    {
        return $this->config->absolutePath('.larapilot/cache/vendor-audit.json');
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function store(array $result): void
    {
        $path = $this->cachePath();

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        if (! is_file(dirname($path).'/.gitignore')) {
            file_put_contents(dirname($path).'/.gitignore', "*\n");
        }

        AtomicFile::write($path, (string) json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }
}
