<?php

declare(strict_types=1);

namespace Larapilot\Services\Upgrade;

/**
 * Code the next PHP versions deprecate or remove, found by pattern in the
 * project's own code (never `vendor/`). It is a first pass that names files
 * and lines; Rector and PHPStan, configured for the target version, are the
 * tools that settle it.
 */
class PhpScanner
{
    /**
     * @var list<string>
     */
    public const FOLDERS = ['app', 'config', 'database', 'routes', 'src', 'tests', 'packages', 'modules', 'Modules', 'bootstrap'];

    private const MAX_FILES = 6000;

    private const MAX_OCCURRENCES = 25;

    /**
     * Rules by the version that introduces the change.
     *
     * @return array<string, array<string, array{level: string, title: string, fix: string, pattern: string}>>
     */
    public static function rules(): array
    {
        return [
            '8.2' => [
                'php82-dollar-brace' => ['level' => 'medium', 'title' => '"${var}" string interpolation (deprecated in 8.2)', 'fix' => 'Write "{$var}".', 'pattern' => '/"[^"\n]*\$\{[A-Za-z_]/'],
                'php82-utf8-encode' => ['level' => 'medium', 'title' => 'utf8_encode() / utf8_decode() (deprecated in 8.2)', 'fix' => 'Use mb_convert_encoding($value, \'UTF-8\', \'ISO-8859-1\').', 'pattern' => '/\butf8_(en|de)code\s*\(/'],
            ],
            '8.3' => [
                'php83-get-class' => ['level' => 'low', 'title' => 'get_class() / get_parent_class() without an argument (deprecated in 8.3)', 'fix' => 'Use static::class / parent::class, or pass $this.', 'pattern' => '/\bget_(parent_)?class\s*\(\s*\)/'],
            ],
            '8.4' => [
                'php84-implicit-nullable' => ['level' => 'medium', 'title' => 'Implicitly nullable parameters (deprecated in 8.4)', 'fix' => 'A typed parameter with a null default must say so: `?Foo $x = null` or `Foo|null $x = null`. Rector fixes it (ExplicitNullableParamTypeRector).', 'pattern' => '/[(,]\s*(?:(?:public|protected|private|readonly)\s+)*(?!mixed\b|null\b|\?)([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)\s+&?\$\w+\s*=\s*null\b/i'],
                'php84-e-strict' => ['level' => 'low', 'title' => 'E_STRICT (deprecated in 8.4)', 'fix' => 'Remove it from error_reporting masks: it has done nothing since PHP 8.0.', 'pattern' => '/\bE_STRICT\b/'],
                'php84-user-error' => ['level' => 'low', 'title' => 'trigger_error() with E_USER_ERROR (deprecated in 8.4)', 'fix' => 'Throw an exception, or call exit() after logging.', 'pattern' => '/trigger_error\s*\([^;]*E_USER_ERROR/'],
                'php84-imap' => ['level' => 'high', 'title' => 'The imap extension (moved out of PHP core in 8.4)', 'fix' => 'Install it from PECL on every server, or move to a pure-PHP client such as webklex/php-imap.', 'pattern' => '/\bimap_[a-z_]+\s*\(/'],
                'php84-pspell-oci' => ['level' => 'high', 'title' => 'pspell, oci8, or PDO_OCI (moved out of PHP core in 8.4)', 'fix' => 'Install the extension from PECL on every server and image.', 'pattern' => '/\b(pspell_[a-z_]+|oci_[a-z_]+)\s*\(|[\'"]oci:/'],
            ],
            '8.5' => [
                'php85-casts' => ['level' => 'low', 'title' => 'Non-canonical casts (integer), (boolean), (double), (binary) (deprecated in 8.5)', 'fix' => 'Write (int), (bool), (float), (string).', 'pattern' => '/\(\s*(integer|boolean|double|binary)\s*\)/i'],
                'php85-close-functions' => ['level' => 'low', 'title' => 'curl_close(), imagedestroy(), finfo_close(), xml_parser_free() (no-ops since 8.0, deprecated in 8.5)', 'fix' => 'Remove the call: the object is freed when it goes out of scope.', 'pattern' => '/\b(curl_close|curl_share_close|imagedestroy|finfo_close|xml_parser_free)\s*\(/'],
            ],
        ];
    }

    /**
     * The rules crossed on the way from one version to another.
     *
     * @return array<string, array{level: string, title: string, fix: string, pattern: string, since: string}>
     */
    public static function crossed(string $from, string $to): array
    {
        $crossed = [];

        foreach (self::rules() as $version => $rules) {
            if (version_compare($version, $from, '>') && version_compare($version, $to, '<=')) {
                foreach ($rules as $id => $rule) {
                    $crossed[$id] = $rule + ['since' => $version];
                }
            }
        }

        return $crossed;
    }

    /**
     * @return array{findings: list<array<string, mixed>>, files_scanned: int, truncated: bool}
     */
    public function scan(string $root, string $from, string $to): array
    {
        $rules = self::crossed($from, $to);
        $findings = [];
        $scanned = 0;
        $truncated = false;

        if ($rules === []) {
            return ['findings' => [], 'files_scanned' => 0, 'truncated' => false];
        }

        foreach ($this->files($root) as $relative) {
            if (++$scanned > self::MAX_FILES) {
                $truncated = true;
                break;
            }

            $path = $root.'/'.$relative;

            if (filesize($path) > 1024 * 1024) {
                continue;
            }

            foreach (preg_split('/\R/', (string) file_get_contents($path)) ?: [] as $index => $line) {
                $trimmed = ltrim($line);

                if ($trimmed === '' || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '#')) {
                    continue;
                }

                foreach ($rules as $id => $rule) {
                    if (preg_match($rule['pattern'], $line) !== 1) {
                        continue;
                    }

                    $findings[$id] ??= ['id' => $id, 'level' => $rule['level'], 'title' => $rule['title'], 'fix' => $rule['fix'], 'since' => $rule['since'], 'count' => 0, 'occurrences' => []];
                    $findings[$id]['count']++;

                    if (count($findings[$id]['occurrences']) < self::MAX_OCCURRENCES) {
                        $findings[$id]['occurrences'][] = ['file' => $relative, 'line' => $index + 1, 'text' => mb_substr(trim($line), 0, 180)];
                    }
                }
            }
        }

        $order = ['blocker' => 0, 'high' => 1, 'medium' => 2, 'low' => 3, 'info' => 4];
        $findings = array_values($findings);
        usort($findings, static fn (array $a, array $b): int => [$order[$a['level']] ?? 9, -$a['count']] <=> [$order[$b['level']] ?? 9, -$b['count']]);

        return ['findings' => $findings, 'files_scanned' => min($scanned, self::MAX_FILES), 'truncated' => $truncated];
    }

    /**
     * @return list<string>
     */
    protected function files(string $root): array
    {
        $files = [];

        foreach (self::FOLDERS as $folder) {
            if (! is_dir($root.'/'.$folder)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveCallbackFilterIterator(
                    new \RecursiveDirectoryIterator($root.'/'.$folder, \FilesystemIterator::SKIP_DOTS),
                    // `bootstrap/cache` is compiled output; an `app/Cache` folder is code.
                    static fn (\SplFileInfo $file): bool => ! in_array($file->getFilename(), ['vendor', 'node_modules', '.git', 'storage'], true)
                        && ! ($file->getFilename() === 'cache' && basename(dirname($file->getPathname())) === 'bootstrap')
                )
            );

            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                    $files[] = ltrim(substr($file->getPathname(), strlen($root)), '/');
                }

                if (count($files) > self::MAX_FILES) {
                    break 2;
                }
            }
        }

        sort($files);

        return $files;
    }
}
