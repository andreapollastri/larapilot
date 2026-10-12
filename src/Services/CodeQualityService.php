<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Composer\InstalledVersions;
use Larapilot\Support\AtomicFile;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class CodeQualityService
{
    public const MIN_LARASTAN_LEVEL = 5;

    public const LARASTAN_PACKAGE = 'larastan/larastan';

    public const PINT_PACKAGE = 'laravel/pint';

    /**
     * The constraints Composer is asked for. Each spans the majors that
     * still install on PHP 8.1 and Laravel 10 and the current ones:
     * Composer picks the newest the project can take — Larastan 2 there,
     * Larastan 3 on PHP 8.2 and Laravel 11.15+ — so no table of versions
     * is kept here.
     *
     * @var array<string, string>
     */
    public const PACKAGE_CONSTRAINTS = [
        self::PINT_PACKAGE => '^1.20',
        self::LARASTAN_PACKAGE => '^2.9|^3.0',
    ];

    /**
     * The Composer scripts offered beside the gate, added only when the
     * project has none of that name.
     *
     * @var array<string, string>
     */
    public const SCRIPTS = [
        'lint' => 'pint',
        'lint:check' => 'pint --test',
        'analyse' => 'phpstan analyse --memory-limit=1G',
    ];

    public const BASELINE_FILE = 'phpstan-baseline.neon';

    /**
     * Pint is handed this many paths per run: a command line stays well
     * under the 32 KB Windows allows.
     */
    protected const PINT_BATCH = 100;

    public function __construct(
        protected ConfigService $config,
        protected GitService $git,
    ) {}

    /**
     * Scaffold the gate: `phpstan.neon.dist`, `pint.json`, and the two dev
     * packages. With Composer the packages and the scripts go through
     * `composer require --dev` and `composer config`, which keep the
     * formatting of `composer.json`; without it they are written into the
     * file, for the next `composer update`.
     *
     * @return array<string, mixed>
     */
    public function install(bool $force = false, bool $runComposer = true): array
    {
        $scaffold = $this->scaffold($force);
        $missing = $this->missingPackages();
        $binary = $runComposer ? $this->composerBinary() : null;

        if ($binary !== null) {
            $packages = $this->requirePackages($binary, $missing);
            $scripts = $this->addScripts($binary);
            $declared = ['merged' => false, 'scripts' => $scripts, 'require_dev' => []];
        } else {
            $declared = $this->declareInManifest($missing);
            $packages = [
                'installed' => false,
                'present' => $missing === [],
                'output' => null,
                'error' => $runComposer && $missing !== []
                    ? 'Composer binary not found: '.implode(' and ', $missing).' are declared in composer.json. Run: composer update '.implode(' ', $missing)
                    : null,
            ];
        }

        return [
            'ok' => true,
            'written' => $scaffold['written'],
            'skipped' => $scaffold['skipped'],
            'composer' => $declared,
            'packages' => $packages,
            'min_larastan_level' => self::MIN_LARASTAN_LEVEL,
        ];
    }

    /**
     * The two config files of the gate, written when missing (or always
     * with `$force`). `composer.json` is not touched: an upgrade calls this.
     *
     * @return array{written: list<string>, skipped: list<string>}
     */
    public function scaffold(bool $force = false): array
    {
        $written = [];
        $skipped = [];

        foreach ([
            'phpstan.neon.dist' => $this->stubPath('phpstan.neon.dist'),
            'pint.json' => $this->stubPath('pint.json'),
        ] as $target => $source) {
            $destination = base_path($target);

            if (is_file($destination) && ! $force) {
                $skipped[] = $target;

                continue;
            }

            AtomicFile::write($destination, (string) file_get_contents($source));
            $written[] = $target;
        }

        return ['written' => $written, 'skipped' => $skipped];
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $composer = $this->readComposerManifest();
        $larastanLevel = $this->detectLarastanLevel();

        return [
            'pint_config' => is_file(base_path('pint.json')),
            'larastan_config' => $this->larastanConfigPath() !== null,
            'larastan_level' => $larastanLevel,
            'larastan_level_ok' => $larastanLevel !== null && $larastanLevel >= self::MIN_LARASTAN_LEVEL,
            'pint_binary' => is_file(base_path('vendor/bin/pint')),
            'phpstan_binary' => is_file(base_path('vendor/bin/phpstan')),
            // Declared in `require` or `require-dev`, or installed — a
            // Larastan that came in through another package counts.
            'composer_require_dev' => [
                self::PINT_PACKAGE => $this->packagePresent($composer, self::PINT_PACKAGE),
                self::LARASTAN_PACKAGE => $this->packagePresent($composer, self::LARASTAN_PACKAGE),
            ],
            'scope' => $this->config->qualityScope(),
            'baseline' => $this->baselineIncluded(),
            'min_larastan_level' => self::MIN_LARASTAN_LEVEL,
        ];
    }

    public function healthy(): bool
    {
        $status = $this->status();

        return $status['pint_config']
            && $status['larastan_config']
            && $status['larastan_level_ok']
            && $status['composer_require_dev'][self::PINT_PACKAGE]
            && $status['composer_require_dev'][self::LARASTAN_PACKAGE];
    }

    /**
     * Pint, then Larastan. Under `quality_scope: CHANGED` Pint reads only
     * the PHP files the branch changed (`--fix` formats only those), and
     * Larastan still reads the project: a change can break a caller in a
     * file it did not touch. The baseline keeps what was there before.
     *
     * @return array{ok: bool, scope: string, files: int|null, baseline: bool, pint: array<string, mixed>, analyse: array<string, mixed>}
     */
    public function run(bool $fixPint = false): array
    {
        $scope = $this->config->qualityScope();
        $files = $scope === 'CHANGED' ? $this->changedPhpFiles() : null;

        if ($scope === 'CHANGED' && $files === null) {
            // Not a Git repository: there is no base to measure a change from.
            $scope = 'ALL';
        }

        $pint = $files === null
            ? $this->runBinary('pint', $fixPint ? [] : ['--test'])
            : $this->runPintOn($files, $fixPint);
        $analyse = $this->runBinary('phpstan', ['analyse', '--no-progress', '--memory-limit=1G']);

        return [
            'ok' => ($pint['ok'] ?? false) && ($analyse['ok'] ?? false),
            'scope' => $scope,
            'files' => $files === null ? null : count($files),
            'baseline' => $this->baselineIncluded(),
            'pint' => $pint,
            'analyse' => $analyse,
        ];
    }

    /**
     * Write the PHPStan baseline — every error the project has today — and
     * include it from the PHPStan config, so the gate holds the code to
     * the level from now on without asking for the past to be fixed first.
     *
     * @return array{ok: bool, file: string, included: bool, config: string|null, output: string}
     */
    public function baseline(): array
    {
        $run = $this->runBinary('phpstan', [
            'analyse',
            '--generate-baseline='.self::BASELINE_FILE,
            '--allow-empty-baseline',
            '--no-progress',
            '--memory-limit=1G',
        ]);

        $config = $this->larastanConfigPath();
        $included = $run['ok'] && $config !== null && $this->includeBaseline($config);

        return [
            'ok' => $run['ok'] && $included,
            'file' => self::BASELINE_FILE,
            'included' => $included,
            'config' => $config === null ? null : basename($config),
            'output' => $run['output'],
        ];
    }

    /**
     * Whether the PHPStan config includes the baseline and the file is there.
     */
    public function baselineIncluded(): bool
    {
        $config = $this->larastanConfigPath();

        if ($config === null || ! is_file(base_path(self::BASELINE_FILE))) {
            return false;
        }

        return preg_match('/^\s*-\s*[\'"]?'.preg_quote(self::BASELINE_FILE, '/').'[\'"]?\s*$/m', (string) file_get_contents($config)) === 1;
    }

    /**
     * The PHP files the branch changed that are still on disk, or null
     * outside a Git repository.
     *
     * @return list<string>|null
     */
    public function changedPhpFiles(): ?array
    {
        $changed = $this->git->changedFiles();

        if ($changed === null) {
            return null;
        }

        return array_values(array_filter($changed, static fn (string $file): bool => str_ends_with($file, '.php')
            && ! str_starts_with($file, 'vendor/')
            && is_file(base_path($file))));
    }

    /**
     * @param  list<string>  $files
     * @return array{ok: bool, exit_code: int, output: string}
     */
    protected function runPintOn(array $files, bool $fix): array
    {
        if ($files === []) {
            return ['ok' => true, 'exit_code' => 0, 'output' => 'No PHP file changed on this branch.'];
        }

        $ok = true;
        $exit = 0;
        $output = [];

        foreach (array_chunk($files, self::PINT_BATCH) as $batch) {
            $run = $this->runBinary('pint', array_merge($fix ? [] : ['--test'], $batch));
            $ok = $ok && $run['ok'];
            $exit = $exit === 0 ? $run['exit_code'] : $exit;
            $output[] = $run['output'];
        }

        return ['ok' => $ok, 'exit_code' => $exit, 'output' => trim(implode("\n", $output))];
    }

    /**
     * Add the baseline to the `includes:` of the PHPStan config, or open
     * that list. The rest of the file stays as it is.
     */
    protected function includeBaseline(string $config): bool
    {
        $content = (string) file_get_contents($config);

        if (preg_match('/^\s*-\s*[\'"]?'.preg_quote(self::BASELINE_FILE, '/').'[\'"]?\s*$/m', $content) === 1) {
            return true;
        }

        if (preg_match('/^includes:[ \t]*\R((?:[ \t]+)-)?/m', $content, $match, PREG_OFFSET_CAPTURE) === 1) {
            $indent = isset($match[1]) ? rtrim($match[1][0], '-') : '    ';
            $at = $match[0][1] + strlen(rtrim($match[0][0], " \t-"));
            $content = substr($content, 0, $at).$indent.'- '.self::BASELINE_FILE."\n".substr($content, $at);
        } else {
            $content = "includes:\n    - ".self::BASELINE_FILE."\n\n".$content;
        }

        AtomicFile::write($config, $content);

        return true;
    }

    /**
     * Envelope view of a quality run. Findings only, unless `$verbose`
     * keeps the raw tool stdout inside the JSON.
     *
     * @param  array{ok: bool, scope?: string, files?: int|null, baseline?: bool, pint: array<string, mixed>, analyse: array<string, mixed>}  $run
     * @return array{ok: bool, scope: string, files: int|null, baseline: bool, pint: array<string, mixed>, analyse: array<string, mixed>}
     */
    public function present(array $run, bool $verbose = false): array
    {
        $pint = $this->presentTool($run['pint'] ?? []);
        $analyse = $this->presentTool($run['analyse'] ?? []);

        if ($verbose) {
            $pint['output'] = (string) ($run['pint']['output'] ?? '');
            $analyse['output'] = (string) ($run['analyse']['output'] ?? '');
        }

        return [
            'ok' => ($pint['ok'] ?? false) && ($analyse['ok'] ?? false),
            'scope' => $run['scope'] ?? 'ALL',
            'files' => $run['files'] ?? null,
            'baseline' => $run['baseline'] ?? false,
            'pint' => $pint,
            'analyse' => $analyse,
        ];
    }

    /**
     * @param  array<string, mixed>  $tool
     * @return array{ok: bool, exit_code: int, summary: string, findings: list<string>}
     */
    public function presentTool(array $tool): array
    {
        $output = (string) ($tool['output'] ?? '');
        $findings = $this->findings($output);
        $ok = (bool) ($tool['ok'] ?? false);

        return [
            'ok' => $ok,
            'exit_code' => (int) ($tool['exit_code'] ?? 1),
            'summary' => $ok ? 'pass' : ($findings[0] ?? 'fail'),
            'findings' => $findings,
        ];
    }

    /**
     * Drop Pint's progress matrix and other glyph-only lines.
     *
     * @return list<string>
     */
    public function findings(string $output): array
    {
        $findings = [];

        foreach (preg_split("/\r\n|\n|\r/", $output) ?: [] as $line) {
            $trim = trim($line);

            if ($trim === '' || $this->isProgressLine($trim)) {
                continue;
            }

            $findings[] = $trim;
        }

        return $findings;
    }

    protected function isProgressLine(string $line): bool
    {
        return preg_match('/^[.\s✓✔✕✖×E]+$/u', $line) === 1
            || preg_match('/^[─━=\-\s]+$/u', $line) === 1
            || preg_match('/^Laravel$/u', $line) === 1;
    }

    /**
     * The gate packages the project neither declares nor has installed.
     *
     * @return list<string>
     */
    public function missingPackages(): array
    {
        $composer = $this->readComposerManifest();

        return array_values(array_filter(
            array_keys(self::PACKAGE_CONSTRAINTS),
            fn (string $package): bool => ! $this->packagePresent($composer, $package)
        ));
    }

    /**
     * @param  list<string>  $missing
     * @return array{installed: bool, present: bool, output: string|null, error: string|null}
     */
    protected function requirePackages(string $binary, array $missing): array
    {
        if ($missing === []) {
            return ['installed' => false, 'present' => true, 'output' => 'Packages already present.', 'error' => null];
        }

        $process = new Process(array_merge(
            [$binary, 'require', '--dev', '--no-interaction', '--no-progress', '--no-scripts'],
            array_map(static fn (string $package): string => $package.':'.self::PACKAGE_CONSTRAINTS[$package], $missing)
        ), base_path());
        $process->setTimeout(600);

        try {
            $process->mustRun();

            return ['installed' => true, 'present' => true, 'output' => trim($process->getOutput()), 'error' => null];
        } catch (ProcessFailedException $exception) {
            return [
                'installed' => false,
                'present' => false,
                'output' => trim($process->getOutput()),
                'error' => (trim($process->getErrorOutput()) ?: $exception->getMessage())
                    ."\nRun: composer require --dev ".$this->requireArguments($missing),
            ];
        }
    }

    /**
     * @return list<string> The scripts added.
     */
    protected function addScripts(string $binary): array
    {
        $existing = $this->readComposerManifest()['scripts'] ?? [];
        $added = [];

        foreach (self::SCRIPTS as $name => $command) {
            if (is_array($existing) && array_key_exists($name, $existing)) {
                continue;
            }

            $process = new Process([$binary, 'config', 'scripts.'.$name, $command], base_path());
            $process->setTimeout(60);
            $process->run();

            if ($process->isSuccessful()) {
                $added[] = $name;
            }
        }

        return $added;
    }

    /**
     * Without Composer: the packages and the scripts are written into
     * `composer.json` for the next `composer update`. Its indentation and
     * the order of its keys are kept, slashes and non-ASCII stay as they
     * are; a package goes in sorted only when `sort-packages` asks for it.
     *
     * @param  list<string>  $missing
     * @return array{merged: bool, scripts: list<string>, require_dev: list<string>}
     */
    protected function declareInManifest(array $missing): array
    {
        $path = base_path('composer.json');
        $decoded = $this->readComposerManifest();

        if ($decoded === null) {
            return ['merged' => false, 'scripts' => [], 'require_dev' => []];
        }

        $scripts = [];

        foreach (self::SCRIPTS as $name => $command) {
            if (! isset($decoded['scripts'][$name])) {
                $decoded['scripts'][$name] = $command;
                $scripts[] = $name;
            }
        }

        foreach ($missing as $package) {
            $decoded['require-dev'][$package] = self::PACKAGE_CONSTRAINTS[$package];
        }

        if ($scripts === [] && $missing === []) {
            return ['merged' => false, 'scripts' => [], 'require_dev' => []];
        }

        if ($missing !== [] && ($decoded['config']['sort-packages'] ?? false) === true) {
            ksort($decoded['require-dev']);
        }

        $raw = (string) file_get_contents($path);
        $indent = preg_match('/^([ \t]+)"/m', $raw, $match) === 1 ? $match[1] : '    ';
        $json = (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($indent !== '    ') {
            $json = (string) preg_replace_callback('/^((?:    )+)/m', static fn (array $m): string => str_repeat($indent, intdiv(strlen($m[1]), 4)), $json);
        }

        AtomicFile::write($path, $json."\n");

        return ['merged' => true, 'scripts' => $scripts, 'require_dev' => $missing];
    }

    /**
     * @param  list<string>  $packages
     */
    protected function requireArguments(array $packages): string
    {
        return implode(' ', array_map(
            static fn (string $package): string => '"'.$package.':'.self::PACKAGE_CONSTRAINTS[$package].'"',
            $packages
        ));
    }

    /**
     * @param  list<string>  $arguments
     * @return array{ok: bool, exit_code: int, output: string}
     */
    protected function runBinary(string $tool, array $arguments = []): array
    {
        $binary = base_path('vendor/bin/'.$tool);

        if (! is_file($binary)) {
            return [
                'ok' => false,
                'exit_code' => 127,
                'output' => $tool.' binary not found. Run composer install after larapilot:install.',
            ];
        }

        $process = new Process(array_merge([$binary], $arguments), base_path());
        $process->setTimeout(600);
        $process->run();

        return [
            'ok' => $process->isSuccessful(),
            'exit_code' => $process->getExitCode() ?? 1,
            'output' => trim($process->getOutput()."\n".$process->getErrorOutput()),
        ];
    }

    protected function stubPath(string $file): string
    {
        return dirname(__DIR__, 2).'/resources/stubs/'.$file;
    }

    protected function larastanConfigPath(): ?string
    {
        foreach (['phpstan.neon', 'phpstan.neon.dist'] as $candidate) {
            if (is_file(base_path($candidate))) {
                return base_path($candidate);
            }
        }

        return null;
    }

    protected function detectLarastanLevel(): ?int
    {
        $path = $this->larastanConfigPath();

        if ($path === null) {
            return null;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return null;
        }

        if (preg_match('/^\s*level:\s*(\d+)\s*$/m', $content, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function readComposerManifest(): ?array
    {
        $path = base_path('composer.json');

        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>|null  $composer
     */
    protected function packagePresent(?array $composer, string $package): bool
    {
        foreach (['require', 'require-dev'] as $section) {
            if (is_array($composer[$section] ?? null) && array_key_exists($package, $composer[$section])) {
                return true;
            }
        }

        return class_exists(InstalledVersions::class) && InstalledVersions::isInstalled($package);
    }

    protected function composerBinary(): ?string
    {
        $candidates = ['composer', 'composer.phar'];

        foreach ($candidates as $candidate) {
            $process = new Process([$candidate, '--version']);
            $process->run();

            if ($process->isSuccessful()) {
                return $candidate;
            }
        }

        return null;
    }
}
