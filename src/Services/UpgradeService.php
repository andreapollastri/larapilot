<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\VersionParser;
use Larapilot\Services\Stack\VersionPins;
use Larapilot\Services\Upgrade\DatabaseScanner;
use Larapilot\Services\Upgrade\PackagistClient;
use Larapilot\Services\Upgrade\PhpScanner;
use Larapilot\Support\AtomicFile;
use Larapilot\Support\ComposerFiles;
use Larapilot\Support\SupportPolicy;

/**
 * How ready the project is to move to another Laravel, another PHP, or
 * another database — and what stands in the way.
 *
 * The lock says what is installed and what each package requires; Packagist
 * says which release of a package supports the target; the code and the
 * files around it say what depends on the version of today. Every finding
 * gets a level (`blocker`, `high`, `medium`, `low`, `info`) so the upgrade
 * skills can report the criticalities before a line is changed.
 *
 * This is a readiness check, not a resolver: Composer has the last word,
 * and the report names the dry run that asks it.
 */
class UpgradeService
{
    public const LEVELS = ['blocker', 'high', 'medium', 'low', 'info'];

    protected VersionParser $parser;

    /**
     * @var array<string, mixed>
     */
    protected array $constraintCache = [];

    public function __construct(
        protected ConfigService $config,
        protected ProjectStackService $stack,
        protected PackagistClient $packagist,
        protected VersionPins $pins,
        protected DatabaseScanner $databaseScanner,
        protected PhpScanner $phpScanner,
    ) {
        $this->parser = new VersionParser;
    }

    /**
     * @param  array{laravel?: string|null, php?: string|null, php_from?: string|null, db?: string|null, db_from?: string|null}  $targets
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException
     */
    public function check(array $targets, bool $offline = false): array
    {
        $laravelTarget = $this->major($targets['laravel'] ?? null);
        $phpTarget = $this->minor($targets['php'] ?? null);
        $dbTarget = $this->database($targets['db'] ?? null);

        if ($laravelTarget === null && $phpTarget === null && $dbTarget === null) {
            throw new \InvalidArgumentException('Name a target: --laravel=13, --php=8.4, or --db=pgsql:17.');
        }

        $composer = $this->stack->composer();
        $root = $this->stack->root();

        if (! $composer->hasLock() && ($laravelTarget !== null || $phpTarget !== null)) {
            throw new \InvalidArgumentException('composer.lock is missing: run composer install first, the check reads what is installed.');
        }

        $current = $this->current($composer, $this->minor($targets['php_from'] ?? null), $targets['db_from'] ?? null, $dbTarget !== null);
        $warnings = [];
        $criticalities = [];
        $result = [
            'current' => $current,
            'target' => [
                'laravel' => $laravelTarget,
                'php' => $phpTarget,
                'database' => $dbTarget,
            ],
            'offline' => $offline,
        ];

        // The PHP every target is measured on: the one asked, else the project's.
        $php = $phpTarget ?? $current['php']['project'];

        if ($laravelTarget !== null) {
            $result['laravel'] = $this->laravel($composer, $current, $laravelTarget, $php, $phpTarget !== null, $offline, $warnings);
            $criticalities = array_merge($criticalities, $result['laravel']['criticalities']);
            unset($result['laravel']['criticalities']);
        }

        if ($phpTarget !== null) {
            $laravelMajor = $laravelTarget ?? $current['laravel']['major'];
            $result['php'] = $this->php($composer, $root, $current, $phpTarget, $laravelMajor, $offline, $warnings);
            $criticalities = array_merge($criticalities, $result['php']['criticalities']);
            unset($result['php']['criticalities']);
        }

        if ($dbTarget !== null) {
            $result['database'] = $this->databaseCheck($root, $current, $dbTarget);
            $criticalities = array_merge($criticalities, $result['database']['criticalities']);
            unset($result['database']['criticalities']);
        }

        $order = array_flip(self::LEVELS);
        usort($criticalities, static fn (array $a, array $b): int => ($order[$a['level']] ?? 9) <=> ($order[$b['level']] ?? 9));

        $counts = array_fill_keys(self::LEVELS, 0);

        foreach ($criticalities as $item) {
            $counts[$item['level']] = ($counts[$item['level']] ?? 0) + 1;
        }

        $result['criticalities'] = $criticalities;
        $result['counts'] = $counts;
        $result['verdict'] = $counts['blocker'] > 0 ? 'blocked' : ($counts['high'] > 0 ? 'attention' : 'ready');
        $result['warnings'] = array_values(array_unique($warnings));

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    protected function current(ComposerFiles $composer, ?string $phpFrom, ?string $dbFrom, bool $withDatabase): array
    {
        $laravel = $composer->version('laravel/framework') ?? ltrim((string) app()->version(), 'v');
        $running = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
        [$project, $source] = $this->projectPhp($composer, $phpFrom, $running);
        $database = null;

        if ($withDatabase) {
            $override = $this->database($dbFrom);
            $facts = $this->stack->database(true);
            $database = [
                'engine' => $override['engine'] ?? ($facts['engine'] ?? null),
                'version' => $override['version'] ?? ($facts['version'] ?? null),
                'connection' => $facts['default'],
                'reachable' => $facts['reachable'],
                'from_option' => $override !== null,
            ];
        }

        return [
            'laravel' => ['version' => $laravel, 'major' => SupportPolicy::cycle('laravel', $laravel)],
            'php' => ['running' => PHP_VERSION, 'minor' => $running, 'project' => $project, 'source' => $source, 'constraint' => $composer->phpConstraint(), 'platform' => $composer->platformPhp()],
            'database' => $database,
        ];
    }

    /**
     * The PHP the project is on: the one named, else the platform Composer
     * resolves for, else the floor of `require.php` — the oldest PHP its
     * environments may run — else the one running this check.
     *
     * @return array{0: string, 1: string}
     */
    protected function projectPhp(ComposerFiles $composer, ?string $named, string $running): array
    {
        if ($named !== null) {
            return [$named, 'option'];
        }

        $platform = SupportPolicy::cycle('php', $composer->platformPhp());

        if ($platform !== null) {
            return [$platform, 'platform'];
        }

        $constraint = $composer->phpConstraint();

        if ($constraint !== null) {
            try {
                $floor = $this->parser->parseConstraints($constraint)->getLowerBound()->getVersion();

                if (preg_match('/^(\d+)\.(\d+)/', $floor, $m) === 1 && (int) $m[1] > 0) {
                    return [$m[1].'.'.$m[2], 'constraint'];
                }
            } catch (\Throwable) {
                // an unreadable constraint falls back to the running PHP
            }
        }

        return [$running, 'running'];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  list<string>  $warnings
     * @return array<string, mixed>
     */
    protected function laravel(ComposerFiles $composer, array $current, string $target, string $php, bool $phpChanges, bool $offline, array &$warnings): array
    {
        $from = (string) $current['laravel']['major'];
        $criticalities = [];

        if ($from !== '' && (int) $target <= (int) $from) {
            throw new \InvalidArgumentException('The project is on Laravel '.$current['laravel']['version'].': name a newer major than '.$from.'.');
        }

        $steps = [];

        for ($major = (int) $from + 1; $major <= (int) $target; $major++) {
            $range = SupportPolicy::laravelPhpRange((string) $major);
            $steps[] = [
                'from' => (string) ($major - 1),
                'to' => (string) $major,
                'php' => $range,
                'guide' => 'https://laravel.com/docs/'.$major.'.x/upgrade',
            ];
        }

        $range = SupportPolicy::laravelPhpRange($target);

        if ($range === null) {
            $criticalities[] = $this->item('medium', 'laravel', 'Laravel '.$target.' is newer than the support table of this Larapilot ('.SupportPolicy::CHECKED.'): its PHP range is not known here.', 'Read https://laravel.com/docs/'.$target.'.x/releases.');
        } elseif (version_compare($php, $range[0], '<')) {
            $running = (string) $current['php']['minor'];

            if (! $phpChanges && $current['php']['source'] === 'constraint' && version_compare($running, $range[0], '>=')) {
                // The floor allows an old PHP; the machine that checks runs a newer one.
                $criticalities[] = $this->item('high', 'php', 'Laravel '.$target.' needs PHP '.$range[0].' or newer; composer.json still allows PHP '.$php.' ('.$current['php']['constraint'].').', 'Raise require.php to ^'.$range[0].' and make sure every server, CI job, and image runs PHP '.$range[0].'+ (larapilot:upgrade-check --php='.$range[0].' lists the pins).');
            } else {
                $criticalities[] = $this->item('blocker', 'php', 'Laravel '.$target.' needs PHP '.$range[0].' or newer; the project '.($phpChanges ? 'targets' : 'is on').' PHP '.$php.'.', 'Upgrade PHP first: /larapilot-php-upgrade to '.$range[0].' or later, then this.');
            }
        } elseif (version_compare($php, $range[1], '>')) {
            $criticalities[] = $this->item('medium', 'php', 'Laravel '.$target.' is supported on PHP '.$range[0].' to '.$range[1].'; PHP '.$php.' is newer than that range.', 'Check the release notes before running it in production on PHP '.$php.'.');
        }

        if (count($steps) > 1) {
            $criticalities[] = $this->item('medium', 'laravel', 'The move crosses '.count($steps).' majors ('.$from.' → '.$target.'): each one has its own upgrade guide.', 'Go one major at a time, with the suite green and a commit between each.');
        }

        // Releases are looked for on the PHP the target needs at least: a
        // project on 8.2 moving to Laravel 13 moves to PHP 8.3 with it.
        $searchPhp = ! $phpChanges && $range !== null && version_compare($php, $range[0], '<') ? $range[0] : $php;
        $dependencies = $this->laravelDependencies($composer, $target, $searchPhp, $offline, $warnings);

        foreach ($dependencies as $dependency) {
            $level = match ($dependency['verdict']) {
                'blocker' => $dependency['dev'] ? 'high' : 'blocker',
                'abandoned' => 'high',
                'private', 'unknown' => 'medium',
                'bump' => 'medium',
                'update' => 'low',
                default => null,
            };

            if ($level !== null) {
                $criticalities[] = $this->item($level, 'dependency', $dependency['name'].': '.$dependency['reason'], $dependency['action']);
            }
        }

        $bumps = array_values(array_filter($dependencies, static fn (array $d): bool => in_array($d['verdict'], ['bump', 'update'], true)));
        $require = ['laravel/framework:^'.$target.'.0'];

        foreach ($bumps as $bump) {
            if ($bump['verdict'] === 'bump' && $bump['candidate'] !== null && ! $bump['dev']) {
                $require[] = $bump['name'].':^'.$this->caret($bump['candidate']);
            }
        }

        $devRequire = [];

        foreach ($bumps as $bump) {
            if ($bump['verdict'] === 'bump' && $bump['candidate'] !== null && $bump['dev']) {
                $devRequire[] = $bump['name'].':^'.$this->caret($bump['candidate']);
            }
        }

        $commands = [
            'composer why-not laravel/framework '.$target.'.0',
            'composer require '.implode(' ', $require).' --with-all-dependencies --dry-run',
        ];

        if ($devRequire !== []) {
            $commands[] = 'composer require --dev '.implode(' ', $devRequire).' --with-all-dependencies --dry-run';
        }

        $counts = array_count_values(array_column($dependencies, 'verdict'));

        return [
            'from' => $current['laravel']['version'],
            'to' => $target,
            'steps' => $steps,
            'php_range' => $range,
            'dependencies' => array_values(array_filter($dependencies, static fn (array $d): bool => $d['verdict'] !== 'agnostic')),
            'agnostic' => $counts['agnostic'] ?? 0,
            'summary' => $counts,
            'commands' => $commands,
            'criticalities' => $criticalities,
        ];
    }

    /**
     * Each direct dependency against a Laravel major.
     *
     * @param  list<string>  $warnings
     * @return list<array<string, mixed>>
     */
    protected function laravelDependencies(ComposerFiles $composer, string $target, string $php, bool $offline, array &$warnings): array
    {
        $laravelConstraint = '^'.$target.'.0';
        $phpConstraint = $php.'.*';
        $installed = $composer->installed();
        $rows = [];

        foreach ($composer->direct() as $name => $direct) {
            if ($name === 'laravel/framework') {
                continue;
            }

            $package = $installed[$name] ?? null;

            if ($package === null) {
                continue;
            }

            $own = $this->lockLaravelConstraints($installed, $name);
            $row = [
                'name' => (string) $package['name'],
                'installed' => (string) $package['version'],
                'constraint' => $direct['constraint'],
                'dev' => $direct['dev'],
                'verdict' => 'agnostic',
                'reason' => 'Does not depend on Laravel.',
                'action' => null,
                'candidate' => null,
                'source' => 'lock',
            ];

            if ($package['abandoned'] !== false) {
                $row['verdict'] = 'abandoned';
                $row['reason'] = 'Abandoned by its maintainers'.(is_string($package['abandoned']) ? '; the suggested replacement is '.$package['abandoned'] : '').'.';
                $row['action'] = is_string($package['abandoned']) ? 'Move to '.$package['abandoned'].' before or during the upgrade.' : 'Find a maintained replacement, or fork it.';
                $rows[] = $row;

                continue;
            }

            if ($own === null) {
                $rows[] = $row;

                continue;
            }

            if ($this->allows($own, $laravelConstraint)) {
                $row['verdict'] = 'ok';
                $row['reason'] = 'The installed '.$package['version'].' already supports Laravel '.$target.'.';
                $rows[] = $row;

                continue;
            }

            if ($offline) {
                $row['verdict'] = 'unknown';
                $row['reason'] = 'The installed '.$package['version'].' does not support Laravel '.$target.'; Packagist was not asked (offline).';
                $row['action'] = 'Run the check online, or composer why-not laravel/framework '.$target.'.0.';
                $rows[] = $row;

                continue;
            }

            try {
                $candidate = $this->candidate((string) $package['name'], $laravelConstraint, $phpConstraint);
            } catch (\RuntimeException $e) {
                $warnings[] = $e->getMessage();
                $row['verdict'] = 'unknown';
                $row['reason'] = 'The installed '.$package['version'].' does not support Laravel '.$target.', and Packagist could not be asked.';
                $row['action'] = 'composer why-not laravel/framework '.$target.'.0';
                $rows[] = $row;

                continue;
            }

            $row['source'] = 'packagist';

            if ($candidate === false) {
                $row['verdict'] = 'private';
                $row['reason'] = 'Not on Packagist (a private repository), and the installed '.$package['version'].' does not support Laravel '.$target.'.';
                $row['action'] = 'Check the vendor\'s release notes for Laravel '.$target.' support'.($name === 'laravel/nova' ? ' (nova.laravel.com/releases)' : '').'.';
            } elseif ($candidate === null) {
                $row['verdict'] = 'blocker';
                $row['reason'] = 'No stable release supports Laravel '.$target.' on PHP '.$php.' yet.';
                $row['action'] = 'Wait for a release, look for a fork or an alternative, or replace it. Check the package\'s issues for a Laravel '.$target.' pull request.';
            } else {
                $row['candidate'] = $candidate['version'];

                if ($this->satisfies($candidate['version'], $direct['constraint'])) {
                    $row['verdict'] = 'update';
                    $row['reason'] = $candidate['version'].' supports Laravel '.$target.' and fits the constraint '.$direct['constraint'].'.';
                    $row['action'] = 'composer update '.$package['name'].' --with-all-dependencies';
                } else {
                    $major = $this->majorOf($candidate['version']) !== $this->majorOf((string) $package['version']);
                    $row['verdict'] = 'bump';
                    $row['reason'] = $candidate['version'].' supports Laravel '.$target.'; the constraint '.$direct['constraint'].' does not allow it'.($major ? ' (a new major: read its upgrade guide)' : '').'.';
                    $row['action'] = 'composer require '.($direct['dev'] ? '--dev ' : '').$package['name'].':^'.$this->caret($candidate['version']).' --with-all-dependencies';
                }
            }

            $rows[] = $row;
        }

        $order = ['blocker' => 0, 'abandoned' => 1, 'private' => 2, 'unknown' => 3, 'bump' => 4, 'update' => 5, 'ok' => 6, 'agnostic' => 7];
        usort($rows, static fn (array $a, array $b): int => [($order[$a['verdict']] ?? 9), $a['name']] <=> [($order[$b['verdict']] ?? 9), $b['name']]);

        return $rows;
    }

    /**
     * The constraints on Laravel an installed package carries, its own or
     * those of the packages of its vendor it requires (`filament/filament`
     * leaves Laravel to `filament/support`). Null when it has none.
     *
     * @param  array<string, array<string, mixed>>  $installed
     * @return list<string>|null
     */
    protected function lockLaravelConstraints(array $installed, string $name, int $depth = 0): ?array
    {
        $package = $installed[$name] ?? null;

        if ($package === null) {
            return null;
        }

        $own = self::laravelConstraintsOf($package['require']);

        if ($own !== [] || $depth >= 2) {
            return $own !== [] ? $own : null;
        }

        $vendor = explode('/', $name)[0];
        $found = [];

        foreach (array_keys($package['require']) as $required) {
            $required = strtolower((string) $required);

            if (! str_starts_with($required, $vendor.'/') || $required === $name) {
                continue;
            }

            $found = array_merge($found, $this->lockLaravelConstraints($installed, $required, $depth + 1) ?? []);
        }

        return $found !== [] ? array_values(array_unique($found)) : null;
    }

    /**
     * @param  array<string, string>  $require
     * @return list<string>
     */
    public static function laravelConstraintsOf(array $require): array
    {
        $constraints = [];

        foreach ($require as $name => $constraint) {
            $name = strtolower((string) $name);

            if ($name === 'laravel/framework' || str_starts_with($name, 'illuminate/')) {
                $constraints[] = (string) $constraint;
            }
        }

        return array_values(array_unique($constraints));
    }

    /**
     * The newest stable release of a package that supports a Laravel and a
     * PHP constraint. `false` when Packagist does not know the package, null
     * when no release fits.
     *
     * @return array{version: string, normalized: string}|false|null
     *
     * @throws \RuntimeException
     */
    protected function candidate(string $package, ?string $laravel, ?string $php): array|false|null
    {
        $releases = $this->packagist->releases($package);

        if ($releases === null) {
            return false;
        }

        foreach ($releases as $release) {
            $require = $release['require'];

            if ($php !== null && isset($require['php']) && ! $this->allows([$require['php']], $php)) {
                continue;
            }

            if ($laravel !== null) {
                $constraints = $this->releaseLaravelConstraints($package, $release);

                if ($constraints === null || ! $this->allows($constraints, $laravel)) {
                    continue;
                }
            }

            return ['version' => $release['version'], 'normalized' => $release['normalized']];
        }

        return null;
    }

    /**
     * The Laravel constraints of one release, following `self.version` and
     * same-vendor requirements one level down.
     *
     * @param  array{version: string, normalized: string, require: array<string, string>}  $release
     * @return list<string>|null
     *
     * @throws \RuntimeException
     */
    protected function releaseLaravelConstraints(string $package, array $release): ?array
    {
        $own = self::laravelConstraintsOf($release['require']);

        if ($own !== []) {
            return $own;
        }

        $vendor = explode('/', strtolower($package))[0];
        $found = [];

        foreach ($release['require'] as $required => $constraint) {
            $required = strtolower((string) $required);

            if (! str_starts_with($required, $vendor.'/') || $required === strtolower($package)) {
                continue;
            }

            $siblings = $this->packagist->releases($required) ?? [];

            foreach ($siblings as $sibling) {
                $matches = $constraint === 'self.version'
                    ? $sibling['normalized'] === $release['normalized']
                    : $this->satisfies($sibling['version'], (string) $constraint);

                if ($matches) {
                    $found = array_merge($found, self::laravelConstraintsOf($sibling['require']));
                    break;
                }
            }
        }

        return $found !== [] ? array_values(array_unique($found)) : null;
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  list<string>  $warnings
     * @return array<string, mixed>
     */
    protected function php(ComposerFiles $composer, string $root, array $current, string $target, ?string $laravelMajor, bool $offline, array &$warnings): array
    {
        $from = (string) $current['php']['project'];
        $criticalities = [];
        $phpConstraint = $target.'.*';

        if (version_compare($target, $from, '<')) {
            $criticalities[] = $this->item('high', 'php', 'PHP '.$target.' is older than the PHP the project is on ('.$from.', from '.$current['php']['source'].').', 'A downgrade is rarely what is wanted: check the target, or pass --php-from.');
        }

        if (version_compare((string) $current['php']['minor'], $target, '<')) {
            $criticalities[] = $this->item('info', 'php', 'This check runs on PHP '.$current['php']['minor'].': the code scan is a first pass, the test suite on PHP '.$target.' is the proof.', 'Run the suite on PHP '.$target.' (Herd, Sail, Docker, or CI) before merging.');
        }

        $support = SupportPolicy::status('php', $target);

        if ($support['state'] === 'eol') {
            $criticalities[] = $this->item('blocker', 'php', 'PHP '.$target.' is past its end of life ('.$support['security_until'].').', 'Choose PHP '.SupportPolicy::latest('php').'.');
        } elseif ($support['ending']) {
            $criticalities[] = $this->item('medium', 'php', 'PHP '.$target.' receives security fixes only until '.$support['security_until'].'.', 'Consider PHP '.SupportPolicy::latest('php').' to avoid a second upgrade soon.');
        } elseif ($support['state'] === 'unknown') {
            $criticalities[] = $this->item('medium', 'php', 'PHP '.$target.' is not in the support table of this Larapilot ('.SupportPolicy::CHECKED.').', 'Check php.net/supported-versions.');
        }

        $range = $laravelMajor !== null ? SupportPolicy::laravelPhpRange($laravelMajor) : null;

        if ($range !== null && (version_compare($target, $range[0], '<') || version_compare($target, $range[1], '>'))) {
            $criticalities[] = $this->item(version_compare($target, $range[0], '<') ? 'blocker' : 'high', 'laravel', 'Laravel '.$laravelMajor.' supports PHP '.$range[0].' to '.$range[1].', not '.$target.'.', version_compare($target, $range[1], '>') ? 'Upgrade Laravel too (/larapilot-laravel-upgrade), or stay within '.$range[1].'.' : 'Choose PHP '.$range[0].' or newer.');
        }

        $constraint = $composer->phpConstraint();

        if ($constraint !== null && ! $this->allows([$constraint], $phpConstraint)) {
            $criticalities[] = $this->item('high', 'composer', 'composer.json requires PHP '.$constraint.', which excludes '.$target.'.', 'Change require.php, e.g. "^'.$target.'".');
        } elseif ($constraint !== null && $current['php']['source'] === 'constraint' && version_compare($from, $target, '<')) {
            $criticalities[] = $this->item('info', 'composer', 'composer.json still allows PHP '.$from.' ('.$constraint.').', 'Raise require.php to ^'.$target.' once every environment runs it, so nobody installs on an older PHP.');
        }

        $platform = $composer->platformPhp();

        if ($platform !== null && SupportPolicy::cycle('php', $platform) !== $target) {
            $criticalities[] = $this->item('high', 'composer', 'composer.json pins config.platform.php to '.$platform.': Composer resolves for that PHP, whatever runs it.', 'composer config platform.php '.$target.'.0');
        }

        // Every installed package, transitive ones included: any of them can stop the move.
        $packages = [];
        $direct = $composer->direct();
        $laravelConstraint = $laravelMajor !== null ? '^'.$laravelMajor.'.0' : null;

        foreach ($composer->installed() as $name => $package) {
            $requires = $package['require']['php'] ?? null;

            if ($requires === null || $this->allows([$requires], $phpConstraint)) {
                continue;
            }

            $row = [
                'name' => (string) $package['name'],
                'installed' => (string) $package['version'],
                'requires_php' => $requires,
                'direct' => isset($direct[$name]),
                'dev' => (bool) $package['dev'],
                'required_by' => isset($direct[$name]) ? [] : array_slice($composer->dependents($name), 0, 5),
                'candidate' => null,
                'verdict' => 'update',
                'action' => null,
            ];

            if (! $offline) {
                try {
                    $candidate = $this->candidate((string) $package['name'], $laravelConstraint !== null && $this->lockLaravelConstraints($composer->installed(), $name) !== null ? $laravelConstraint : null, $phpConstraint);
                } catch (\RuntimeException $e) {
                    $warnings[] = $e->getMessage();
                    $candidate = null;
                    $row['verdict'] = 'unknown';
                }

                if ($candidate === false) {
                    $row['verdict'] = 'private';
                } elseif (is_array($candidate)) {
                    $row['candidate'] = $candidate['version'];
                } elseif ($row['verdict'] !== 'unknown') {
                    $row['verdict'] = 'blocker';
                }
            } else {
                $row['verdict'] = 'unknown';
            }

            $row['action'] = match ($row['verdict']) {
                'blocker' => 'No stable release supports PHP '.$target.': replace it, or wait.',
                'private' => 'Not on Packagist: check the vendor for PHP '.$target.' support.',
                'unknown' => 'composer why-not php '.$target,
                default => $row['direct']
                    ? 'composer require '.($row['dev'] ? '--dev ' : '').$row['name'].':^'.$this->caret((string) $row['candidate']).' --with-all-dependencies'
                    : 'Update what requires it'.($row['required_by'] !== [] ? ' ('.implode(', ', $row['required_by']).')' : '').': composer update '.$row['name'].' --with-all-dependencies',
            };

            $packages[] = $row;

            $level = match ($row['verdict']) {
                'blocker' => $row['dev'] ? 'high' : 'blocker',
                'private', 'unknown' => 'medium',
                default => 'low',
            };

            $criticalities[] = $this->item($level, 'dependency', $row['name'].' '.$row['installed'].' requires PHP '.$requires.'.', $row['action']);
        }

        $pins = $this->pins->scan($root, ['php']);
        $behind = VersionPins::behind($pins, 'php', $target);

        foreach ($behind as $pin) {
            if ($pin['file'] === 'composer.json') {
                continue;
            }

            $criticalities[] = $this->item('high', 'pin', $pin['file'].':'.$pin['line'].' pins PHP '.$pin['value'].'.', 'Change it to '.$target.' in the same change.');
        }

        $code = $this->phpScanner->scan($root, $from, $target);

        foreach ($code['findings'] as $finding) {
            $criticalities[] = $this->item($finding['level'], 'code', $finding['title'].' — '.$finding['count'].' place(s).', $finding['fix']);
        }

        $removed = [];

        if (version_compare($from, '8.4', '<') && version_compare($target, '8.4', '>=')) {
            // What the project declares: the extensions of this machine say nothing of its servers.
            foreach (['imap', 'pspell', 'oci8', 'pdo_oci'] as $extension) {
                if (isset($composer->json()['require']['ext-'.$extension]) || isset($composer->json()['require-dev']['ext-'.$extension])) {
                    $removed[] = $extension;
                    $criticalities[] = $this->item('high', 'extension', 'ext-'.$extension.' left PHP core in 8.4.', 'Install it from PECL on every server and image, or drop its use.');
                }
            }
        }

        return [
            'from' => $from,
            'to' => $target,
            'support' => $support,
            'laravel_range' => $range,
            'constraint' => $constraint,
            'platform' => $platform,
            'packages' => $packages,
            'pins' => $pins,
            'pins_behind' => $behind,
            'code' => $code,
            'extensions_removed' => $removed,
            'guides' => array_values(array_map(
                static fn (string $version): string => 'https://www.php.net/manual/en/migration'.str_replace('.', '', $version).'.php',
                array_filter(array_keys(PhpScanner::rules()), static fn (string $version): bool => version_compare($version, $from, '>') && version_compare($version, $target, '<='))
            )),
            'commands' => array_values(array_filter([
                'composer why-not php '.$target,
                $platform !== null ? 'composer config platform.php '.$target.'.0' : null,
                'composer update --with-all-dependencies --dry-run',
            ])),
            'criticalities' => $criticalities,
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array{engine: string, version: string|null}  $target
     * @return array<string, mixed>
     */
    protected function databaseCheck(string $root, array $current, array $target): array
    {
        $engine = (string) ($current['database']['engine'] ?? '');
        $version = $current['database']['version'] ?? null;
        $criticalities = [];

        if ($engine === '') {
            $criticalities[] = $this->item('medium', 'database', 'The engine of today is not known.', 'Pass it: --db-from=mysql:8.0.');
            $engine = 'mysql';
        }

        if ($version === null) {
            $criticalities[] = $this->item('medium', 'database', 'The version of the database of today is not known (the server did not answer).', 'Pass it with --db-from='.$engine.':8.0 for the rules that depend on it.');
        }

        $support = $target['engine'] !== 'sqlite' && $target['version'] !== null ? SupportPolicy::status($target['engine'], $target['version']) : null;

        if ($support !== null && $support['state'] === 'eol') {
            $criticalities[] = $this->item('blocker', 'database', SupportPolicy::label($target['engine']).' '.$target['version'].' is past its end of life.', 'Choose '.SupportPolicy::label($target['engine']).' '.SupportPolicy::latest($target['engine']).'.');
        } elseif ($support !== null && $support['ending']) {
            $criticalities[] = $this->item('medium', 'database', SupportPolicy::label($target['engine']).' '.$target['version'].' is supported only until '.$support['security_until'].'.', 'Consider '.SupportPolicy::latest($target['engine']).'.');
        }

        $scan = $this->databaseScanner->scan($root, $engine, $version, $target['engine'], $target['version']);

        foreach ($scan['findings'] as $finding) {
            $criticalities[] = $this->item($finding['level'], 'database', $finding['title'].' — '.$finding['count'].' place(s).', $finding['fix']);
        }

        foreach ($scan['checklist'] as $item) {
            if (($item['raise'] ?? false) === true) {
                $criticalities[] = $this->item($item['level'], 'operations', $item['title'].'.', $item['detail']);
            }
        }

        $kinds = [DatabaseScanner::family($engine), DatabaseScanner::family($target['engine'])];
        $pins = array_values(array_filter($this->pins->scan($root, ['mysql', 'mariadb', 'pgsql']), static fn (array $pin): bool => in_array($pin['kind'], $kinds, true)));

        foreach ($pins as $pin) {
            if ($pin['kind'] !== $target['engine'] || ($target['version'] !== null && SupportPolicy::cycle($target['engine'], $pin['value']) !== SupportPolicy::cycle($target['engine'], $target['version']))) {
                $criticalities[] = $this->item('medium', 'pin', $pin['file'].':'.$pin['line'].' runs '.SupportPolicy::label($pin['kind']).' '.$pin['value'].'.', 'Point it to '.SupportPolicy::label($target['engine']).($target['version'] ? ' '.$target['version'] : '').' in the same change.');
            }
        }

        return [
            'from' => ['engine' => $engine, 'version' => $version, 'connection' => $current['database']['connection'] ?? null],
            'to' => $target,
            'kind' => $scan['kind'],
            'support' => $support,
            'scan' => $scan,
            'pins' => $pins,
            'driver_extension' => match ($target['engine']) {
                'pgsql' => ['name' => 'pdo_pgsql', 'loaded' => extension_loaded('pdo_pgsql')],
                'sqlite' => ['name' => 'pdo_sqlite', 'loaded' => extension_loaded('pdo_sqlite')],
                'sqlsrv' => ['name' => 'pdo_sqlsrv', 'loaded' => extension_loaded('pdo_sqlsrv')],
                default => ['name' => 'pdo_mysql', 'loaded' => extension_loaded('pdo_mysql')],
            },
            'criticalities' => $criticalities,
        ];
    }

    /**
     * Write the check as Markdown under `paths.upgrades`.
     *
     * @param  array<string, mixed>  $check
     */
    public function writeReport(array $check): string
    {
        $directory = rtrim((string) $this->config->setupInfo()['paths']['upgrades'], '/');
        $path = $directory.'/'.now()->format('Y-m-d').'-readiness-'.$this->slug($check).'.md';

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        AtomicFile::write($path, $this->markdown($check));

        return $path;
    }

    /**
     * @param  array<string, mixed>  $check
     */
    public function markdown(array $check): string
    {
        $lines = ['# Upgrade readiness — '.$this->title($check), ''];
        $lines[] = 'Checked on '.now()->format('Y-m-d H:i').' by `php artisan larapilot:upgrade-check`. Verdict: **'.strtoupper((string) $check['verdict']).'**.';
        $lines[] = '';
        $lines[] = '| Level | Count |';
        $lines[] = '| --- | ---: |';

        foreach (self::LEVELS as $level) {
            $lines[] = '| '.ucfirst($level).' | '.($check['counts'][$level] ?? 0).' |';
        }

        if ($check['warnings'] !== []) {
            $lines[] = '';
            $lines[] = '> '.implode("\n> ", $check['warnings']);
        }

        $lines[] = '';
        $lines[] = '## Criticalities';
        $lines[] = '';

        if ($check['criticalities'] === []) {
            $lines[] = '_None found. Composer still has the last word: run the dry run below._';
        } else {
            $lines[] = '| Level | Area | Finding | What to do |';
            $lines[] = '| --- | --- | --- | --- |';

            foreach ($check['criticalities'] as $item) {
                $lines[] = '| '.ucfirst($item['level']).' | '.$item['area'].' | '.$this->cell($item['message']).' | '.$this->cell((string) $item['hint']).' |';
            }
        }

        if (isset($check['laravel'])) {
            $laravel = $check['laravel'];
            $lines[] = '';
            $lines[] = '## Laravel '.$laravel['from'].' → '.$laravel['to'];
            $lines[] = '';

            foreach ($laravel['steps'] as $step) {
                $lines[] = '- Laravel '.$step['from'].' → '.$step['to'].($step['php'] ? ' (PHP '.$step['php'][0].' – '.$step['php'][1].')' : '').': '.$step['guide'];
            }

            $lines[] = '';
            $lines[] = '### Dependencies';
            $lines[] = '';
            $lines[] = '| Package | Installed | Constraint | Verdict | Candidate | Why |';
            $lines[] = '| --- | --- | --- | --- | --- | --- |';

            foreach ($laravel['dependencies'] as $dependency) {
                $lines[] = '| '.$dependency['name'].($dependency['dev'] ? ' _(dev)_' : '').' | '.$dependency['installed'].' | `'.$dependency['constraint'].'` | '.$dependency['verdict'].' | '.($dependency['candidate'] ?? '—').' | '.$this->cell($dependency['reason']).' |';
            }

            $lines[] = '';
            $lines[] = $laravel['agnostic'].' other direct dependencies do not depend on Laravel.';
            $lines[] = '';
            $lines[] = '### Ask Composer';
            $lines[] = '';
            $lines[] = '```bash';
            $lines = array_merge($lines, $laravel['commands']);
            $lines[] = '```';
        }

        if (isset($check['php'])) {
            $php = $check['php'];
            $lines[] = '';
            $lines[] = '## PHP '.$php['from'].' → '.$php['to'];
            $lines[] = '';
            $lines[] = '- Support: '.SupportPolicy::stateLabel($php['support']['state']).($php['support']['security_until'] ? ', security fixes until '.$php['support']['security_until'] : '').'.';

            if ($php['laravel_range']) {
                $lines[] = '- Laravel supports PHP '.$php['laravel_range'][0].' – '.$php['laravel_range'][1].'.';
            }

            foreach ($php['guides'] as $guide) {
                $lines[] = '- Migration guide: '.$guide;
            }

            if ($php['packages'] !== []) {
                $lines[] = '';
                $lines[] = '### Packages that exclude PHP '.$php['to'];
                $lines[] = '';
                $lines[] = '| Package | Installed | Requires PHP | Candidate | What to do |';
                $lines[] = '| --- | --- | --- | --- | --- |';

                foreach ($php['packages'] as $package) {
                    $lines[] = '| '.$package['name'].' | '.$package['installed'].' | `'.$package['requires_php'].'` | '.($package['candidate'] ?? '—').' | '.$this->cell((string) $package['action']).' |';
                }
            }

            $lines[] = '';
            $lines[] = '### Files that pin PHP';
            $lines[] = '';

            foreach ($php['pins'] === [] ? [] : $php['pins'] as $pin) {
                $lines[] = '- `'.$pin['file'].':'.$pin['line'].'` — '.$pin['value'];
            }

            if ($php['pins'] === []) {
                $lines[] = '_None found._';
            }

            $lines = array_merge($lines, $this->occurrences('Code to change', $php['code']['findings']));
        }

        if (isset($check['database'])) {
            $database = $check['database'];
            $lines[] = '';
            $lines[] = '## Database '.$database['scan']['transition'];
            $lines[] = '';
            $lines[] = 'Kind: '.$database['kind'].'. '.$database['scan']['files_scanned'].' files read.';
            $lines = array_merge($lines, $this->occurrences('Code to change', $database['scan']['findings']));
            $lines[] = '';
            $lines[] = '### Checklist';
            $lines[] = '';

            foreach ($database['scan']['checklist'] as $item) {
                $lines[] = '- [ ] **'.$item['title'].'** ('.$item['level'].') — '.$item['detail'];
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     * @return list<string>
     */
    protected function occurrences(string $heading, array $findings): array
    {
        $lines = ['', '### '.$heading, ''];

        if ($findings === []) {
            $lines[] = '_Nothing found by the scan._';

            return $lines;
        }

        foreach ($findings as $finding) {
            $lines[] = '#### '.$finding['title'].' — '.$finding['level'].', '.$finding['count'].' place(s)';
            $lines[] = '';
            $lines[] = $finding['fix'];
            $lines[] = '';

            foreach ($finding['occurrences'] as $occurrence) {
                $lines[] = '- `'.$occurrence['file'].':'.$occurrence['line'].'` — `'.str_replace('`', "'", $occurrence['text']).'`';
            }

            if ($finding['count'] > count($finding['occurrences'])) {
                $lines[] = '- … and '.($finding['count'] - count($finding['occurrences'])).' more.';
            }

            $lines[] = '';
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $check
     */
    protected function title(array $check): string
    {
        $parts = [];

        if (isset($check['laravel'])) {
            $parts[] = 'Laravel '.$check['laravel']['from'].' → '.$check['laravel']['to'];
        }

        if (isset($check['php'])) {
            $parts[] = 'PHP '.$check['php']['from'].' → '.$check['php']['to'];
        }

        if (isset($check['database'])) {
            $parts[] = 'Database '.$check['database']['scan']['transition'];
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $check
     */
    protected function slug(array $check): string
    {
        $parts = [];

        if (isset($check['laravel'])) {
            $parts[] = 'laravel-'.$this->majorOf((string) $check['laravel']['from']).'-to-'.$check['laravel']['to'];
        }

        if (isset($check['php'])) {
            $parts[] = 'php-'.$check['php']['from'].'-to-'.$check['php']['to'];
        }

        if (isset($check['database'])) {
            $parts[] = 'db-'.$check['database']['from']['engine'].'-to-'.$check['database']['to']['engine'].($check['database']['to']['version'] ? '-'.$check['database']['to']['version'] : '');
        }

        return (string) preg_replace('/[^a-z0-9.-]+/', '-', strtolower(implode('-', $parts)));
    }

    /**
     * @return array{level: string, area: string, message: string, hint: string|null}
     */
    protected function item(string $level, string $area, string $message, ?string $hint = null): array
    {
        return ['level' => $level, 'area' => $area, 'message' => $message, 'hint' => $hint];
    }

    /**
     * Whether every constraint allows at least one version of the target.
     *
     * @param  list<string>  $constraints
     */
    public function allows(array $constraints, string $target): bool
    {
        $wanted = $this->constraint($target);

        if ($wanted === null) {
            return false;
        }

        foreach ($constraints as $constraint) {
            $parsed = $this->constraint($constraint);

            // A constraint Composer cannot read (`self.version`) decides nothing.
            if ($parsed === null) {
                continue;
            }

            if (! $parsed->matches($wanted)) {
                return false;
            }
        }

        return true;
    }

    public function satisfies(string $version, string $constraint): bool
    {
        $parsed = $this->constraint($constraint);

        if ($parsed === null) {
            return false;
        }

        try {
            $provided = $this->parser->parseConstraints($this->parser->normalize($version));
        } catch (\Throwable) {
            return false;
        }

        return $parsed->matches($provided);
    }

    protected function constraint(string $constraint): ?ConstraintInterface
    {
        if (array_key_exists($constraint, $this->constraintCache)) {
            return $this->constraintCache[$constraint];
        }

        try {
            return $this->constraintCache[$constraint] = $this->parser->parseConstraints($constraint);
        } catch (\Throwable) {
            return $this->constraintCache[$constraint] = null;
        }
    }

    protected function caret(string $version): string
    {
        return preg_match('/^(\d+)\.(\d+)/', $version, $m) === 1 ? $m[1].'.'.$m[2] : $version;
    }

    protected function majorOf(string $version): string
    {
        return preg_match('/^v?(\d+)/', $version, $m) === 1 ? $m[1] : $version;
    }

    protected function major(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (preg_match('/^v?(\d{1,2})(?:\.[\dx*]+)*$/i', trim($value), $m) !== 1) {
            throw new \InvalidArgumentException('--laravel takes a major version, like 13.');
        }

        return $m[1];
    }

    protected function minor(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (preg_match('/^(\d)\.(\d{1,2})(?:\.\d+)?$/', trim($value), $m) !== 1) {
            throw new \InvalidArgumentException('--php takes a version like 8.4.');
        }

        return $m[1].'.'.$m[2];
    }

    /**
     * `pgsql:17`, `postgres`, `mysql:8.4`, `mariadb:11.4`, `sqlite`.
     *
     * @return array{engine: string, version: string|null}|null
     */
    protected function database(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (preg_match('/^([a-z]+)(?:[:@ ]v?(\d+(?:\.\d+)?))?$/i', trim($value), $m) !== 1) {
            throw new \InvalidArgumentException('--db takes an engine and a version, like pgsql:17, mysql:8.4, or mariadb:11.4.');
        }

        $engine = DatabaseScanner::family($m[1]);

        if (! in_array(strtolower($m[1]), ['mysql', 'mariadb', 'pgsql', 'postgres', 'postgresql', 'sqlite', 'sqlsrv', 'mssql', 'sqlserver'], true)) {
            throw new \InvalidArgumentException('Unknown database engine "'.$m[1].'": use mysql, mariadb, pgsql, sqlite, or sqlsrv.');
        }

        return ['engine' => $engine, 'version' => $m[2] ?? null];
    }

    protected function cell(string $text): string
    {
        return str_replace(['|', "\n"], ['\\|', ' '], $text);
    }
}
