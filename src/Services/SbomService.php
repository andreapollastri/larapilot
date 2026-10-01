<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Illuminate\Support\Str;
use Larapilot\LarapilotServiceProvider;
use Larapilot\Services\Sbom\NodeLockfiles;
use Larapilot\Support\ComposerFiles;

/**
 * The software bill of materials of the project: every package it ships,
 * read from the lockfiles — Composer for Laravel, the JavaScript lockfile of
 * this repository, and the one of the external frontend when the companion
 * links it (an app in its own repository, or the monorepo it lives in).
 *
 * Nothing is installed or downloaded: the lockfiles are the truth. The
 * export is Markdown for people and CycloneDX 1.5 JSON for tools.
 */
class SbomService
{
    /**
     * Licenses that oblige whoever distributes the software to share code.
     *
     * @var list<string>
     */
    public const STRONG_COPYLEFT = ['GPL', 'AGPL', 'SSPL', 'EUPL', 'OSL', 'CPAL', 'RPL'];

    /**
     * @var list<string>
     */
    public const WEAK_COPYLEFT = ['LGPL', 'MPL', 'EPL', 'CDDL', 'CPL', 'MS-RL'];

    /**
     * SPDX identifiers common enough to be written as `id` in CycloneDX;
     * anything else is written as a name.
     *
     * @var list<string>
     */
    private const SPDX_IDS = [
        'MIT', 'MIT-0', 'ISC', '0BSD', 'BSD-2-Clause', 'BSD-3-Clause', 'Apache-2.0', 'Unlicense', 'CC0-1.0', 'CC-BY-4.0', 'CC-BY-3.0',
        'Zlib', 'Python-2.0', 'BlueOak-1.0.0', 'WTFPL', 'Artistic-2.0', 'BSL-1.0', 'PostgreSQL', 'OFL-1.1',
        'LGPL-2.1-only', 'LGPL-2.1-or-later', 'LGPL-3.0-only', 'LGPL-3.0-or-later', 'MPL-2.0', 'EPL-1.0', 'EPL-2.0', 'CDDL-1.0',
        'GPL-2.0-only', 'GPL-2.0-or-later', 'GPL-3.0-only', 'GPL-3.0-or-later', 'AGPL-3.0-only', 'AGPL-3.0-or-later',
    ];

    /**
     * The inventory of this request: the lockfiles are read once.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $memo = null;

    public function __construct(
        protected ConfigService $config,
        protected NodeLockfiles $lockfiles,
    ) {}

    /**
     * Every inventory with its components, and the totals.
     *
     * @return array<string, mixed>
     */
    public function inventory(bool $fresh = false): array
    {
        if ($this->memo !== null && ! $fresh) {
            return $this->memo;
        }

        $root = rtrim($this->config->projectRoot(), '/');
        $inventories = [$this->composerInventory($root)];

        $assets = $this->nodeInventory('assets', 'Laravel frontend assets', $root, 'This repository');

        if ($assets !== null) {
            $inventories[] = $assets;
        }

        $companion = $this->companionInventory();

        if ($companion !== null) {
            $inventories[] = $companion;
        }

        $components = [];

        foreach ($inventories as $inventory) {
            foreach ($inventory['components'] as $component) {
                $components[] = $component;
            }
        }

        return $this->memo = [
            'project' => $this->projectName(),
            'generated_at' => now()->toIso8601String(),
            'inventories' => array_map(static function (array $inventory): array {
                $summary = $inventory;
                unset($summary['components']);

                return $summary;
            }, $inventories),
            'components' => $components,
            'totals' => $this->totals($components),
            'licenses' => $this->licenseSummary($components),
            'fingerprint' => $this->fingerprint($inventories),
        ];
    }

    /**
     * A digest of the lockfiles: when it changes, a vulnerability check
     * made before speaks of other packages.
     *
     * @param  list<array<string, mixed>>|null  $inventories
     */
    public function fingerprint(?array $inventories = null): string
    {
        $parts = [];

        foreach ($inventories ?? $this->inventory()['inventories'] as $inventory) {
            $path = is_string($inventory['path'] ?? null) ? $inventory['path'] : null;
            $parts[] = $inventory['id'].':'.($path !== null && is_file($path) ? md5_file($path) : '-');
        }

        return substr(sha1(implode('|', $parts)), 0, 16);
    }

    /**
     * @return array<string, mixed>
     */
    protected function composerInventory(string $root): array
    {
        $composer = new ComposerFiles($root);
        $direct = $composer->direct();
        $components = [];

        foreach ($composer->installed() as $key => $package) {
            $components[] = [
                'inventory' => 'composer',
                'ecosystem' => 'composer',
                'name' => (string) $package['name'],
                'version' => (string) $package['version'],
                'scope' => $package['dev'] ? 'dev' : 'prod',
                'direct' => isset($direct[$key]),
                'constraint' => $direct[$key]['constraint'] ?? null,
                'license' => $package['license'],
                'license_class' => self::licenseClass($package['license']),
                'type' => (string) $package['type'],
                'description' => Str::limit((string) $package['description'], 140),
                'homepage' => $package['homepage'] ?? $package['source'],
                'abandoned' => $package['abandoned'],
                'purl' => self::purl('composer', (string) $package['name'], (string) $package['version']),
            ];
        }

        return [
            'id' => 'composer',
            'label' => 'Laravel (Composer)',
            'ecosystem' => 'composer',
            'where' => 'This repository',
            'file' => $composer->hasLock() ? 'composer.lock' : null,
            'path' => $composer->hasLock() ? $root.'/composer.lock' : null,
            'manager' => 'composer',
            'readable' => $composer->hasLock(),
            'error' => $composer->hasLock() ? null : ($composer->hasJson() ? 'composer.lock is missing: run composer install.' : 'No composer.json.'),
            'note' => null,
            'components' => $components,
            'count' => count($components),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function nodeInventory(string $id, string $label, string $root, string $where, ?string $note = null): ?array
    {
        $read = $this->lockfiles->read($root);

        if ($read['file'] === null && ! is_file($root.'/package.json')) {
            return null;
        }

        $components = [];

        foreach ($read['packages'] as $package) {
            $components[] = [
                'inventory' => $id,
                'ecosystem' => 'npm',
                'name' => $package['name'],
                'version' => $package['version'],
                'scope' => $package['dev'] === null ? null : ($package['dev'] ? 'dev' : 'prod'),
                'direct' => $package['direct'],
                'constraint' => $package['constraint'] ?? null,
                'license' => $package['license'],
                'license_class' => self::licenseClass($package['license']),
                'type' => 'library',
                'description' => '',
                'homepage' => 'https://www.npmjs.com/package/'.$package['name'],
                'abandoned' => false,
                'purl' => self::purl('npm', $package['name'], $package['version']),
            ];
        }

        return [
            'id' => $id,
            'label' => $label,
            'ecosystem' => 'npm',
            'where' => $where,
            'file' => $read['file'],
            'path' => $read['file'] !== null ? $root.'/'.$read['file'] : null,
            'manager' => $read['manager'],
            'readable' => $read['readable'],
            'error' => $read['file'] === null ? 'package.json has no lockfile next to it: install once so the versions are fixed.' : $read['error'],
            'note' => $note,
            'components' => $components,
            'count' => count($components),
        ];
    }

    /**
     * The frontend the companion links: its own lockfile, else the one of
     * the workspace it is built in.
     *
     * @return array<string, mixed>|null
     */
    protected function companionInventory(): ?array
    {
        $frontend = $this->config->frontend();

        if (! $frontend['configured'] || ! is_string($frontend['repo_path'])) {
            return null;
        }

        $repository = rtrim($frontend['repo_path'], '/');
        $name = basename($repository);
        $root = $repository;
        $note = null;

        if ($this->lockfiles->find($repository) === null && is_string($frontend['workspace_path'] ?? null) && $frontend['workspace_path'] !== '' && $this->lockfiles->find(rtrim($frontend['workspace_path'], '/')) !== null) {
            $root = rtrim($frontend['workspace_path'], '/');
            $note = 'Read from the lockfile of the workspace '.basename($root).': it holds every project of the monorepo, not only this app.';
        } elseif (is_file($repository.'/nx.json') || is_file($repository.'/pnpm-workspace.yaml') || is_file($repository.'/turbo.json') || is_file($repository.'/lerna.json')) {
            $note = 'A monorepo: the lockfile holds every project of the workspace'.($frontend['projects'] !== [] ? ', not only '.implode(', ', $frontend['projects']) : '').'.';
        }

        return $this->nodeInventory('companion', 'Frontend companion', $root, $name.($frontend['stack'] ? ' · '.$frontend['stack'] : ''), $note);
    }

    /**
     * @param  list<array<string, mixed>>  $components
     * @return array<string, mixed>
     */
    protected function totals(array $components): array
    {
        $totals = ['components' => count($components), 'direct' => 0, 'prod' => 0, 'dev' => 0, 'abandoned' => 0, 'by_ecosystem' => []];

        foreach ($components as $component) {
            $totals['direct'] += $component['direct'] ? 1 : 0;
            $totals['prod'] += $component['scope'] === 'prod' ? 1 : 0;
            $totals['dev'] += $component['scope'] === 'dev' ? 1 : 0;
            $totals['abandoned'] += $component['abandoned'] !== false ? 1 : 0;
            $totals['by_ecosystem'][$component['ecosystem']] = ($totals['by_ecosystem'][$component['ecosystem']] ?? 0) + 1;
        }

        return $totals;
    }

    /**
     * @param  list<array<string, mixed>>  $components
     * @return array{classes: array<string, int>, licenses: list<array{license: string, count: int, class: string}>, copyleft: list<array{name: string, version: string, license: string, class: string, inventory: string, scope: string|null}>}
     */
    protected function licenseSummary(array $components): array
    {
        $classes = ['permissive' => 0, 'weak-copyleft' => 0, 'strong-copyleft' => 0, 'unknown' => 0];
        $licenses = [];
        $copyleft = [];

        foreach ($components as $component) {
            $class = $component['license_class'];
            $classes[$class] = ($classes[$class] ?? 0) + 1;
            $label = $component['license'] !== [] ? implode(' OR ', $component['license']) : 'Not declared';
            $licenses[$label] ??= ['license' => $label, 'count' => 0, 'class' => $class];
            $licenses[$label]['count']++;

            if (in_array($class, ['weak-copyleft', 'strong-copyleft'], true)) {
                $copyleft[] = ['name' => $component['name'], 'version' => $component['version'], 'license' => $label, 'class' => $class, 'inventory' => $component['inventory'], 'scope' => $component['scope']];
            }
        }

        $licenses = array_values($licenses);
        usort($licenses, static fn (array $a, array $b): int => [$b['count'], $a['license']] <=> [$a['count'], $b['license']]);

        return ['classes' => $classes, 'licenses' => $licenses, 'copyleft' => $copyleft];
    }

    /**
     * `permissive`, `weak-copyleft`, `strong-copyleft`, or `unknown`. A
     * choice of licenses (`MIT OR GPL-3.0`) takes the most permissive one;
     * licenses that hold together (`MIT AND GPL-3.0`) take the strictest.
     *
     * @param  list<string>  $licenses
     */
    public static function licenseClass(array $licenses): string
    {
        if ($licenses === []) {
            return 'unknown';
        }

        $best = null;
        $rank = ['permissive' => 0, 'weak-copyleft' => 1, 'strong-copyleft' => 2, 'unknown' => 3];

        foreach ($licenses as $license) {
            foreach (preg_split('/\s+OR\s+|\s*\/\s*/i', trim($license, '() ')) ?: [] as $choice) {
                $class = null;

                foreach (preg_split('/\s+AND\s+/i', trim($choice, '() ')) ?: [] as $part) {
                    $partClass = self::classOf($part);

                    // `unknown` says nothing about the terms: a known license next to it decides.
                    if ($class === null || $class === 'unknown' || ($partClass !== 'unknown' && $rank[$partClass] > $rank[$class])) {
                        $class = $partClass;
                    }
                }

                $class ??= 'unknown';

                if ($best === null || $rank[$class] < $rank[$best]) {
                    $best = $class;
                }
            }
        }

        return $best ?? 'unknown';
    }

    protected static function classOf(string $license): string
    {
        $upper = strtoupper(trim($license, '() '));

        if ($upper === '' || in_array($upper, ['UNLICENSED', 'PROPRIETARY', 'SEE LICENSE IN LICENSE', 'NONE', 'UNKNOWN'], true) || str_starts_with($upper, 'SEE LICENSE')) {
            return 'unknown';
        }

        foreach (self::WEAK_COPYLEFT as $prefix) {
            if (str_starts_with($upper, $prefix)) {
                return 'weak-copyleft';
            }
        }

        foreach (self::STRONG_COPYLEFT as $prefix) {
            if (str_starts_with($upper, $prefix)) {
                return 'strong-copyleft';
            }
        }

        return 'permissive';
    }

    /**
     * The package URL of a component (purl-spec): `pkg:composer/vendor/name@1.0.0`,
     * `pkg:npm/%40scope/name@1.0.0`.
     */
    public static function purl(string $ecosystem, string $name, string $version): string
    {
        $name = strtolower($name);

        if ($ecosystem === 'npm' && str_starts_with($name, '@')) {
            $name = '%40'.substr($name, 1);
        }

        return 'pkg:'.$ecosystem.'/'.$name.'@'.rawurlencode($version);
    }

    /**
     * @param  array<string, mixed>|null  $audit  the last vulnerability check, when there is one
     */
    public function markdown(?array $audit = null): string
    {
        $inventory = $this->inventory();
        $vulnerable = $this->vulnerabilityIndex($audit);
        $lines = ['# SBOM — '.$inventory['project'], ''];
        $lines[] = 'Software bill of materials generated on '.now()->format('Y-m-d H:i').' by Larapilot '.LarapilotServiceProvider::VERSION.' from the lockfiles.';
        $lines[] = '';
        $lines[] = '## Summary';
        $lines[] = '';
        $lines[] = '| Inventory | Where | Lockfile | Components |';
        $lines[] = '| --- | --- | --- | ---: |';

        foreach ($inventory['inventories'] as $item) {
            $lines[] = '| '.$item['label'].' | '.$this->cell((string) $item['where']).' | '.($item['file'] !== null ? '`'.$item['file'].'`' : '—').' | '.$item['count'].' |';
        }

        $totals = $inventory['totals'];
        $lines[] = '';
        $lines[] = '- Components: **'.$totals['components'].'** ('.$totals['direct'].' direct, '.$totals['prod'].' production, '.$totals['dev'].' development)';

        if ($totals['abandoned'] > 0) {
            $lines[] = '- Abandoned packages: **'.$totals['abandoned'].'**';
        }

        foreach ($inventory['inventories'] as $item) {
            if ($item['error'] !== null) {
                $lines[] = '- '.$item['label'].': '.$item['error'];
            }

            if ($item['note'] !== null) {
                $lines[] = '- '.$item['label'].': '.$item['note'];
            }
        }

        $lines[] = '';
        $lines[] = '## Vulnerabilities';
        $lines[] = '';

        if ($audit === null) {
            $lines[] = '_Not checked yet: run `php artisan larapilot:vendor-audit`._';
        } else {
            $lines[] = 'Checked against '.$audit['source'].' on '.substr((string) $audit['checked_at'], 0, 16).($audit['stale'] ?? false ? ' — **the lockfiles changed since**' : '').'.';
            $lines[] = '';
            $lines[] = '| Severity | Open | Waived | In the backlog |';
            $lines[] = '| --- | ---: | ---: | ---: |';

            foreach (VendorAuditService::SEVERITIES as $severity) {
                $lines[] = '| '.ucfirst($severity).' | '.($audit['counts'][$severity] ?? 0).' | '.($audit['states']['waived'][$severity] ?? 0).' | '.($audit['states']['in_backlog'][$severity] ?? 0).' |';
            }

            if ($audit['findings'] !== []) {
                $lines[] = '';
                $lines[] = '| Id | Severity | Package | Version | Fixed in | Decision | Summary |';
                $lines[] = '| --- | --- | --- | --- | --- | --- | --- |';

                foreach ($audit['findings'] as $finding) {
                    $lines[] = '| ['.$finding['id'].']('.$finding['url'].') | '.ucfirst($finding['severity']).' | '.$finding['package'].' | '.$finding['version'].' | '.($finding['fixed'] ?? '—').' | '.$this->decision($finding).' | '.$this->cell((string) $finding['summary']).' |';
                }
            }
        }

        $licenses = $inventory['licenses'];
        $lines[] = '';
        $lines[] = '## Licenses';
        $lines[] = '';
        $lines[] = '| License | Components | Kind |';
        $lines[] = '| --- | ---: | --- |';

        foreach ($licenses['licenses'] as $license) {
            $lines[] = '| '.$this->cell($license['license']).' | '.$license['count'].' | '.$license['class'].' |';
        }

        if ($licenses['copyleft'] !== []) {
            $lines[] = '';
            $lines[] = '### Copyleft components';
            $lines[] = '';

            foreach ($licenses['copyleft'] as $component) {
                $lines[] = '- '.$component['name'].' '.$component['version'].' — '.$component['license'].' ('.$component['class'].($component['scope'] ? ', '.$component['scope'] : '').')';
            }
        }

        foreach ($inventory['inventories'] as $item) {
            $lines[] = '';
            $lines[] = '## '.$item['label'].' — '.$item['count'].' components';
            $lines[] = '';

            $rows = array_values(array_filter($inventory['components'], static fn (array $component): bool => $component['inventory'] === $item['id']));

            if ($rows === []) {
                $lines[] = '_None._';

                continue;
            }

            $lines[] = '| Package | Version | Scope | Direct | License | Vulnerabilities |';
            $lines[] = '| --- | --- | --- | --- | --- | --- |';

            foreach ($rows as $component) {
                $ids = $vulnerable[$component['ecosystem'].'|'.strtolower($component['name']).'|'.$component['version']] ?? [];
                $lines[] = '| '.$component['name'].($component['abandoned'] !== false ? ' _(abandoned)_' : '').' | '.$component['version'].' | '.($component['scope'] ?? '—').' | '.($component['direct'] ? 'yes' : '').' | '.$this->cell($component['license'] !== [] ? implode(' OR ', $component['license']) : '—').' | '.($ids !== [] ? implode(', ', $ids) : '').' |';
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * CycloneDX 1.5, with the vulnerabilities of the last check.
     *
     * @param  array<string, mixed>|null  $audit
     * @return array<string, mixed>
     */
    public function cyclonedx(?array $audit = null): array
    {
        $inventory = $this->inventory();
        $components = [];

        foreach ($inventory['components'] as $component) {
            // One package in two inventories — the assets and the companion — is one component: a bom-ref is unique.
            if (isset($components[$component['purl']])) {
                $components[$component['purl']]['properties'][] = ['name' => 'larapilot:inventory', 'value' => $component['inventory']];

                if ($component['scope'] !== 'dev') {
                    $components[$component['purl']]['scope'] = 'required';
                }

                continue;
            }

            $name = $component['name'];
            $group = null;

            if (str_contains($name, '/')) {
                [$group, $name] = explode('/', $name, 2);
            }

            $entry = array_filter([
                'type' => 'library',
                'bom-ref' => $component['purl'],
                'group' => $group,
                'name' => $name,
                'version' => $component['version'],
                'purl' => $component['purl'],
                'scope' => $component['scope'] === 'dev' ? 'optional' : 'required',
                'licenses' => $this->cyclonedxLicenses($component['license']),
                'properties' => [
                    ['name' => 'larapilot:inventory', 'value' => $component['inventory']],
                    ['name' => 'larapilot:direct', 'value' => $component['direct'] ? 'true' : 'false'],
                ],
            ], static fn (mixed $value): bool => $value !== null && $value !== []);

            $components[$component['purl']] = $entry;
        }

        $components = array_values($components);

        $bom = [
            'bomFormat' => 'CycloneDX',
            'specVersion' => '1.5',
            'serialNumber' => 'urn:uuid:'.Str::uuid()->toString(),
            'version' => 1,
            'metadata' => [
                'timestamp' => now()->toIso8601String(),
                'tools' => ['components' => [['type' => 'application', 'name' => 'larapilot', 'version' => LarapilotServiceProvider::VERSION]]],
                'component' => ['type' => 'application', 'bom-ref' => 'project', 'name' => $inventory['project']],
            ],
            'components' => $components,
        ];

        if ($audit !== null && $audit['findings'] !== []) {
            $bom['vulnerabilities'] = array_map(static fn (array $finding): array => array_filter([
                'bom-ref' => 'vuln-'.$finding['id'].'-'.$finding['purl'],
                'id' => $finding['id'],
                'source' => ['name' => 'OSV', 'url' => $finding['url']],
                'ratings' => [['severity' => $finding['severity'] === 'unknown' ? 'unknown' : $finding['severity'], 'source' => ['name' => 'OSV']]],
                'description' => $finding['summary'],
                'published' => $finding['published'] ?? null,
                'advisories' => array_map(static fn (string $alias): array => ['title' => $alias, 'url' => str_starts_with($alias, 'CVE-') ? 'https://www.cve.org/CVERecord?id='.$alias : 'https://osv.dev/vulnerability/'.$alias], $finding['aliases']),
                'affects' => [['ref' => $finding['purl']]],
                'analysis' => match ($finding['state']) {
                    'waived' => ['state' => 'exploitable', 'response' => ['will_not_fix'], 'detail' => (string) ($finding['reason'] ?? '')],
                    'in_backlog' => ['state' => 'exploitable', 'response' => ['update'], 'detail' => 'In the backlog as '.($finding['spec'] ?? '')],
                    default => null,
                },
            ], static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== ''), $audit['findings']);
        }

        return $bom;
    }

    /**
     * @param  list<string>  $licenses
     * @return list<array<string, mixed>>
     */
    protected function cyclonedxLicenses(array $licenses): array
    {
        if ($licenses === []) {
            return [];
        }

        if (count($licenses) === 1 && preg_match('/\s(OR|AND|WITH)\s/', $licenses[0]) === 1) {
            return [['expression' => trim($licenses[0], '() ')]];
        }

        return array_map(
            static fn (string $license): array => ['license' => in_array($license, self::SPDX_IDS, true) ? ['id' => $license] : ['name' => $license]],
            $licenses
        );
    }

    public function markdownFilename(): string
    {
        return Str::slug($this->projectName()).'-sbom-'.now()->format('Y-m-d').'.md';
    }

    public function cyclonedxFilename(): string
    {
        return Str::slug($this->projectName()).'-sbom-'.now()->format('Y-m-d').'.cdx.json';
    }

    protected function projectName(): string
    {
        $composer = new ComposerFiles(rtrim($this->config->projectRoot(), '/'));
        $name = (string) config('app.name', '');

        return $name !== '' && $name !== 'Laravel' ? $name : ($composer->name() ?? ($name !== '' ? $name : 'Project'));
    }

    /**
     * @param  array<string, mixed>|null  $audit
     * @return array<string, list<string>>
     */
    protected function vulnerabilityIndex(?array $audit): array
    {
        $index = [];

        foreach ($audit['findings'] ?? [] as $finding) {
            $index[$finding['ecosystem'].'|'.strtolower($finding['package']).'|'.$finding['version']][] = $finding['id'];
        }

        return $index;
    }

    /**
     * @param  array<string, mixed>  $finding
     */
    protected function decision(array $finding): string
    {
        return match ($finding['state']) {
            'waived' => 'Waived: '.$this->cell((string) ($finding['reason'] ?? '')),
            'in_backlog' => 'In the backlog ('.($finding['spec'] ?? '').')',
            default => 'Open',
        };
    }

    protected function cell(string $text): string
    {
        return str_replace(['|', "\n"], ['\\|', ' '], $text);
    }
}
