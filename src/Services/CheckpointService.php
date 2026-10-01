<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Illuminate\Support\Facades\Artisan;
use Larapilot\Support\AtomicFile;
use Larapilot\Support\ComposerFiles;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Checkpoint (`andreapollastri/checkpoint`) — the static security scanner
 * for Laravel: CVEs in the dependencies, hardcoded secrets, injection
 * patterns, weak configuration, end-of-life versions. Larapilot runs its
 * `checkpoint:scan --json`, keeps the result, and shows it under Security.
 *
 * The scan is the package's own: Larapilot never installs it, never edits
 * its configuration, and reads the suppressions from it. The last result
 * and the trend of the scans stay in `.larapilot/cache/checkpoint/` — the
 * details can quote code, secrets included, so they are not committed.
 */
class CheckpointService
{
    public const PACKAGE = 'andreapollastri/checkpoint';

    public const STATUSES = ['fail', 'warn', 'pass'];

    private const HISTORY = 30;

    /**
     * The area of each built-in check, by the name it reports.
     *
     * @var array<string, string>
     */
    public const AREAS = [
        'Composer CVE Audit' => 'Dependencies',
        'NPM CVE Audit' => 'Dependencies',
        'Package Freshness (Supply Chain)' => 'Dependencies',
        'Package Freshness' => 'Dependencies',
        'Supply Chain Tooling' => 'Dependencies',
        'Suspicious Vendor Autoload' => 'Dependencies',
        'EOL Versions' => 'Dependencies',
        'Environment Configuration' => 'Configuration',
        '.gitignore Sensitive Files' => 'Configuration',
        'File Permissions' => 'Configuration',
        'CORS Configuration' => 'Configuration',
        'Session & Cookie Security' => 'Configuration',
        'Sensitive Data Exposure' => 'Configuration',
        'TLS Certificate Verification' => 'Configuration',
        'CSRF Protection' => 'Code',
        'Hardcoded Secrets' => 'Code',
        'SQL Injection Risks' => 'Code',
        'Mass Assignment Vulnerabilities' => 'Code',
        'XSS (Cross-Site Scripting) Risks' => 'Code',
        'Open Redirect Risks' => 'Code',
        'Command Injection Risks' => 'Code',
        'Insecure Deserialization' => 'Code',
        'Debug Functions in Production Code' => 'Code',
        'SSRF Risks' => 'Code',
        'Path Traversal Risks' => 'Code',
        'Weak Cryptography' => 'Code',
        'Insecure RNG' => 'Code',
    ];

    public function __construct(protected ConfigService $config) {}

    /**
     * Whether `checkpoint:scan` can run in this application.
     */
    public function installed(): bool
    {
        try {
            return array_key_exists('checkpoint:scan', Artisan::all());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * The installed version, from the lock.
     */
    public function version(): ?string
    {
        return (new ComposerFiles(rtrim($this->config->projectRoot(), '/')))->version(self::PACKAGE);
    }

    /**
     * Hashes the project silenced in `config/checkpoint.php`.
     */
    public function suppressed(): int
    {
        $suppressed = config('checkpoint.suppressed');

        return is_array($suppressed) ? count($suppressed) : 0;
    }

    /**
     * Run the scan and keep the result.
     *
     * @param  list<string>  $only
     * @param  list<string>  $skip
     * @return array<string, mixed>
     *
     * @throws \RuntimeException
     */
    public function scan(array $only = [], array $skip = []): array
    {
        if (! $this->installed()) {
            throw new \RuntimeException('Checkpoint is not installed: composer require --dev '.self::PACKAGE);
        }

        $started = microtime(true);

        try {
            // Run on an output of its own: Artisan::call() would take the
            // place of the last output of whoever called this command.
            $command = Artisan::all()['checkpoint:scan'];
            $definition = $command->getDefinition();
            $parameters = ['--json' => true];

            // A Checkpoint that does not know the option must not be handed it.
            if ($only !== [] && $definition->hasOption('only')) {
                $parameters['--only'] = implode(',', $only);
            }

            if ($skip !== [] && $definition->hasOption('skip')) {
                $parameters['--skip'] = implode(',', $skip);
            }

            $buffer = new BufferedOutput;
            $exit = $command->run(new ArrayInput($parameters), $buffer);
            $output = $buffer->fetch();
        } catch (\Throwable $e) {
            throw new \RuntimeException('checkpoint:scan failed: '.$e->getMessage(), 0, $e);
        }

        $checks = $this->parse($output);

        if ($checks === null) {
            throw new \RuntimeException('checkpoint:scan answered no JSON (exit code '.$exit.'). Run it by hand to see why: php artisan checkpoint:scan');
        }

        $result = [
            'scanned_at' => now()->toIso8601String(),
            'seconds' => round(microtime(true) - $started, 1),
            'version' => $this->version(),
            'partial' => $only !== [] || $skip !== [],
            'only' => $only,
            'skip' => $skip,
            'checks' => $checks,
        ];

        $this->store($result);

        return $this->summarize($result);
    }

    /**
     * The checks of a JSON answer, or null when there is none.
     *
     * @return list<array{check: string, status: string, message: string, details: list<array{text: string, hash: string|null}>, area: string}>|null
     */
    public function parse(string $output): ?array
    {
        // The list opens at `[{` (or `[]`): a progress line before it may
        // carry a bracket of its own (`[3/26]`, `[OK]`, an ANSI escape).
        $start = preg_match('/\[\s*[{\]]/', $output, $m, PREG_OFFSET_CAPTURE) === 1 ? (int) $m[0][1] : false;
        $end = strrpos($output, ']');

        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        $decoded = json_decode(substr($output, $start, $end - $start + 1), true);

        // A trailing `]` of some later line: walk back to the one that closes the list.
        while (! is_array($decoded) && ($end = strrpos(substr($output, 0, $end), ']')) !== false && $end > $start) {
            $decoded = json_decode(substr($output, $start, $end - $start + 1), true);
        }

        if (! is_array($decoded)) {
            return null;
        }

        $checks = [];

        foreach ($decoded as $row) {
            if (! is_array($row) || ! is_string($row['check'] ?? null)) {
                continue;
            }

            $status = strtolower((string) ($row['status'] ?? 'pass'));
            $details = [];
            $hashes = is_array($row['hashes'] ?? null) ? array_values($row['hashes']) : [];

            foreach (array_values(is_array($row['details'] ?? null) ? $row['details'] : []) as $index => $detail) {
                $details[] = ['text' => (string) $detail, 'hash' => isset($hashes[$index]) ? (string) $hashes[$index] : null];
            }

            $checks[] = [
                'check' => $row['check'],
                'status' => in_array($status, self::STATUSES, true) ? $status : 'warn',
                'message' => (string) ($row['message'] ?? ''),
                'details' => $details,
                'area' => self::AREAS[$row['check']] ?? 'Other',
            ];
        }

        return $checks;
    }

    /**
     * The last scan, with the trend, or null when none was made here.
     *
     * @return array<string, mixed>|null
     */
    public function latest(): ?array
    {
        $path = $this->latestPath();

        if (! is_file($path)) {
            return null;
        }

        $result = json_decode((string) file_get_contents($path), true);

        return is_array($result) && is_array($result['checks'] ?? null) ? $this->summarize($result) : null;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function summarize(array $result): array
    {
        $counts = array_fill_keys(self::STATUSES, 0);
        $areas = [];
        $findings = 0;

        foreach ($result['checks'] as $check) {
            $counts[$check['status']] = ($counts[$check['status']] ?? 0) + 1;
            $areas[$check['area']] ??= ['area' => $check['area'], 'fail' => 0, 'warn' => 0, 'pass' => 0];
            $areas[$check['area']][$check['status']]++;
            $findings += $check['status'] !== 'pass' ? max(1, count($check['details'])) : 0;
        }

        $rank = array_flip(self::STATUSES);
        $checks = $result['checks'];
        usort($checks, static fn (array $a, array $b): int => [$rank[$a['status']] ?? 9, $a['area'], $a['check']] <=> [$rank[$b['status']] ?? 9, $b['area'], $b['check']]);

        $verdict = $counts['fail'] > 0 ? 'FAIL' : ($counts['warn'] > 0 ? 'WARN' : 'PASS');

        return [
            'scanned_at' => $result['scanned_at'],
            'seconds' => $result['seconds'] ?? null,
            'version' => $result['version'] ?? null,
            'partial' => (bool) ($result['partial'] ?? false),
            'only' => $result['only'] ?? [],
            'skip' => $result['skip'] ?? [],
            'verdict' => $verdict,
            'counts' => $counts,
            'total' => count($checks),
            'findings' => $findings,
            'areas' => array_values($areas),
            'checks' => $checks,
            'history' => $this->history(),
            'suppressed' => $this->suppressed(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(): array
    {
        $path = $this->historyPath();

        if (! is_file($path)) {
            return [];
        }

        $history = json_decode((string) file_get_contents($path), true);

        return is_array($history) ? array_values(array_filter($history, 'is_array')) : [];
    }

    /**
     * The scan for the team, under `paths.security`.
     *
     * @param  array<string, mixed>  $summary
     * @return array{path: string, relative: string}
     */
    public function writeReport(array $summary): array
    {
        $directory = rtrim((string) $this->config->setupInfo()['paths']['security'], '/');
        $path = $directory.'/checkpoint.md';

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
        $lines = ['# Checkpoint — static security scan', ''];
        $lines[] = 'Scanned on '.substr((string) $summary['scanned_at'], 0, 16).' with andreapollastri/checkpoint '.($summary['version'] ?? '').'. Verdict: **'.$summary['verdict'].'** — '.$summary['counts']['pass'].' passed, '.$summary['counts']['warn'].' warnings, '.$summary['counts']['fail'].' failed ('.$summary['total'].' checks).';

        if ($summary['partial']) {
            $lines[] = '';
            $lines[] = '> A partial scan'.($summary['only'] !== [] ? ': only '.implode(', ', $summary['only']) : '').($summary['skip'] !== [] ? '; skipped '.implode(', ', $summary['skip']) : '').'.';
        }

        if ($summary['suppressed'] > 0) {
            $lines[] = '';
            $lines[] = $summary['suppressed'].' finding(s) are suppressed in `config/checkpoint.php`.';
        }

        $lines[] = '';
        $lines[] = '| Check | Area | Result | Message |';
        $lines[] = '| --- | --- | --- | --- |';

        foreach ($summary['checks'] as $check) {
            $lines[] = '| '.$check['check'].' | '.$check['area'].' | '.strtoupper($check['status']).' | '.str_replace(['|', "\n"], ['\\|', ' '], $check['message']).' |';
        }

        foreach ($summary['checks'] as $check) {
            if ($check['status'] === 'pass' || $check['details'] === []) {
                continue;
            }

            $lines[] = '';
            $lines[] = '## '.strtoupper($check['status']).' — '.$check['check'];
            $lines[] = '';
            $lines[] = $check['message'];
            $lines[] = '';

            foreach ($check['details'] as $detail) {
                $lines[] = '- '.$detail['text'].($detail['hash'] !== null ? ' `['.$detail['hash'].']`' : '');
            }
        }

        $lines[] = '';
        $lines[] = 'A false positive is silenced by adding its hash to `suppressed` in `config/checkpoint.php` (`php artisan vendor:publish --tag=checkpoint-config`).';

        return implode("\n", $lines)."\n";
    }

    protected function directory(): string
    {
        return $this->config->absolutePath('.larapilot/cache/checkpoint');
    }

    protected function latestPath(): string
    {
        return $this->directory().'/latest.json';
    }

    protected function historyPath(): string
    {
        return $this->directory().'/history.json';
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function store(array $result): void
    {
        $directory = $this->directory();

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (! is_file(dirname($directory).'/.gitignore')) {
            file_put_contents(dirname($directory).'/.gitignore', "*\n");
        }

        AtomicFile::write($this->latestPath(), (string) json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        $counts = array_fill_keys(self::STATUSES, 0);

        foreach ($result['checks'] as $check) {
            $counts[$check['status']]++;
        }

        $history = $this->history();
        $history[] = ['scanned_at' => $result['scanned_at'], 'partial' => $result['partial']] + $counts;

        AtomicFile::write($this->historyPath(), (string) json_encode(array_slice($history, -self::HISTORY), JSON_UNESCAPED_SLASHES));
    }
}
