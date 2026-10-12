<?php

declare(strict_types=1);

namespace Larapilot\Support;

use Larapilot\Services\ConfigService;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class MockupAssetResolver
{
    /**
     * Design systems shipped with the package, in the order a bare
     * `tokens.css` is looked up.
     *
     * @var list<string>
     */
    public const PACKAGED = [
        'filament',
        'nova',
        'adminlte',
        'starter-kit',
        'shadcn',
        'bootstrap-5',
        'tailwind',
        'material-3',
        'nordic-saas',
        'fluent',
        'modern',
        'bold-california',
        'berlin',
    ];

    /**
     * Older or shorter token file names that still resolve to a packaged
     * folder: `bootstrap-tokens.css` was the name before `bootstrap-5/`
     * took its own.
     *
     * @var array<string, string>
     */
    protected const TOKEN_ALIASES = [
        'bootstrap' => 'bootstrap-5',
        'material' => 'material-3',
        'shadcn-ui' => 'shadcn',
    ];

    /**
     * @var array{key: string, slugs: list<string>}|null
     */
    protected ?array $slugCache = null;

    public function __construct(protected ConfigService $config) {}

    /**
     * Every design system the project holds: the packaged ones first, then
     * the folders added beside them — a custom system from the interview,
     * the as-is system of an adopted project, an uploaded reference — that
     * carry a `tokens.css`, a `README.md`, or an `html/index.html`.
     *
     * @return list<string>
     */
    public function designSystemSlugs(): array
    {
        $root = $this->designSystemsRoot();
        $key = $root.'@'.(is_dir($root) ? (string) filemtime($root) : '-');

        if ($this->slugCache !== null && $this->slugCache['key'] === $key) {
            return $this->slugCache['slugs'];
        }

        $slugs = self::PACKAGED;

        if (is_dir($root)) {
            foreach (scandir($root) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..' || in_array($entry, $slugs, true)) {
                    continue;
                }

                if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $entry) !== 1) {
                    continue;
                }

                $dir = $root.DIRECTORY_SEPARATOR.$entry;

                if (! is_dir($dir)) {
                    continue;
                }

                if (is_file($dir.'/tokens.css') || is_file($dir.'/README.md') || is_file($dir.'/html/index.html')) {
                    $slugs[] = $entry;
                }
            }
        }

        $this->slugCache = ['key' => $key, 'slugs' => $slugs];

        return $slugs;
    }

    /**
     * The design system a `{slug}-tokens.css` file name points at; null when
     * the name is not one of a known system.
     */
    public function designSystemForTokenBasename(string $basename): ?string
    {
        $basename = strtolower(basename(str_replace('\\', '/', $basename)));

        if (! str_ends_with($basename, '-tokens.css')) {
            return null;
        }

        $slug = substr($basename, 0, -strlen('-tokens.css'));

        foreach ($this->designSystemSlugs() as $known) {
            if (strtolower($known) === $slug) {
                return $known;
            }
        }

        return self::TOKEN_ALIASES[$slug] ?? null;
    }

    public function designSystemsRoot(): string
    {
        $config = $this->config->resolve();

        return rtrim($this->config->absolutePath($config['paths']['design_systems'] ?? '.larapilot/design-systems/'), DIRECTORY_SEPARATOR);
    }

    public function mockupsRoot(): string
    {
        $config = $this->config->resolve();

        return rtrim($this->config->absolutePath($config['paths']['mockups'] ?? '.larapilot/mockups/'), DIRECTORY_SEPARATOR);
    }

    public function designSystemsAssetUrl(string $relativePath): string
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
        $prefix = trim((string) config('larapilot.mockup_assets_route.prefix', 'mockup-assets'), '/');

        return '/'.$prefix.'/design-systems/'.$relativePath;
    }

    public function mockupAssetUrl(string $spec, string $relativePath): string
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
        $prefix = trim((string) config('larapilot.mockups_route.prefix', 'mockups'), '/');

        return '/'.$prefix.'/'.$spec.'/'.$relativePath;
    }

    public function resolveMockupFile(string $mockupRoot, string $relativePath): ?string
    {
        if (str_contains($relativePath, "\0") || str_contains($relativePath, '..')) {
            return null;
        }

        $absolute = $mockupRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $realRoot = realpath($mockupRoot);
        $realFile = realpath($absolute);

        if ($realRoot === false || $realFile === false || ! is_file($realFile)) {
            return null;
        }

        if (! str_starts_with($realFile, $realRoot.DIRECTORY_SEPARATOR) && $realFile !== $realRoot) {
            return null;
        }

        return $realFile;
    }

    public function resolveDesignSystemsFile(string $relativePath): ?string
    {
        if (str_contains($relativePath, "\0") || str_contains($relativePath, '..')) {
            return null;
        }

        $root = $this->designSystemsRoot();
        $absolute = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $realRoot = realpath($root);
        $realFile = realpath($absolute);

        if ($realRoot === false || $realFile === false || ! is_file($realFile)) {
            return null;
        }

        if (! str_starts_with($realFile, $realRoot.DIRECTORY_SEPARATOR) && $realFile !== $realRoot) {
            return null;
        }

        return $realFile;
    }

    public function resolveDesignSystemsFileByBasename(string $basename): ?string
    {
        $basename = basename(str_replace('\\', '/', $basename));

        if ($basename === '') {
            return null;
        }

        $mapped = $this->mapTokenBasenameToDesignSystemsPath($basename);

        if ($mapped !== null) {
            $match = $this->resolveDesignSystemsFile($mapped);

            if ($match !== null) {
                return $match;
            }
        }

        foreach ($this->designSystemSlugs() as $slug) {
            $match = $this->resolveDesignSystemsFile($slug.'/'.$basename);

            if ($match !== null) {
                return $match;
            }
        }

        return null;
    }

    /**
     * @return array{path: string, url: string}|null
     */
    public function resolveAssetReference(
        string $spec,
        string $mockupRoot,
        string $currentDir,
        string $reference,
        ?string $designSystem = null,
    ): ?array {
        $reference = trim($reference);

        if ($reference === '' || $this->isAbsoluteReference($reference)) {
            return null;
        }

        $relativePath = $this->normalizeRelativePath($currentDir, $reference);

        if ($relativePath === null) {
            return null;
        }

        $mockupFile = $this->resolveMockupFile($mockupRoot, $relativePath);

        if ($mockupFile !== null) {
            return [
                'path' => $mockupFile,
                'url' => $this->mockupAssetUrl($spec, $relativePath),
            ];
        }

        $designSystemsPath = $this->mapToDesignSystemsPath(
            $mockupRoot,
            $relativePath,
            $designSystem ?? $this->detectDesignSystem($mockupRoot)
        );

        if ($designSystemsPath !== null) {
            $designSystemsFile = $this->resolveDesignSystemsFile($designSystemsPath);

            if ($designSystemsFile !== null) {
                return [
                    'path' => $designSystemsFile,
                    'url' => $this->designSystemsAssetUrl($designSystemsPath),
                ];
            }
        }

        $basename = basename($relativePath);
        $mockupMatch = $this->findMockupFileByBasename($mockupRoot, $basename);

        if ($mockupMatch !== null) {
            return [
                'path' => $mockupMatch['path'],
                'url' => $this->mockupAssetUrl($spec, $mockupMatch['relative']),
            ];
        }

        $fallback = $this->resolveDesignSystemsFileByBasename($basename);

        if ($fallback !== null) {
            $relative = ltrim(str_replace($this->designSystemsRoot(), '', $fallback), DIRECTORY_SEPARATOR);

            return [
                'path' => $fallback,
                'url' => $this->designSystemsAssetUrl(str_replace(DIRECTORY_SEPARATOR, '/', $relative)),
            ];
        }

        return null;
    }

    public function resolveOrphanAsset(string $filename): ?string
    {
        $filename = basename(str_replace('\\', '/', $filename));

        if ($filename === '') {
            return null;
        }

        // The name is matched as written: `*`, `?`, `[` in it are characters,
        // not patterns.
        $pattern = str_replace(['\\', '*', '?', '['], ['\\\\', '\*', '\?', '\['], $filename);

        foreach (glob($this->mockupsRoot().DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.$pattern) ?: [] as $match) {
            if (($contained = $this->containedMockupFile($match)) !== null) {
                return $contained;
            }
        }

        foreach (glob($this->mockupsRoot().DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.$pattern) ?: [] as $match) {
            if (($contained = $this->containedMockupFile($match)) !== null) {
                return $contained;
            }
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->mockupsRoot(), RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === $filename && ($contained = $this->containedMockupFile($file->getPathname())) !== null) {
                return $contained;
            }
        }

        return $this->resolveDesignSystemsFileByBasename($filename);
    }

    /**
     * A match under the mockups folder, resolved: a link that leads out of
     * the folder is not served from it.
     */
    protected function containedMockupFile(string $match): ?string
    {
        $root = realpath($this->mockupsRoot());
        $real = realpath($match);

        if ($root === false || $real === false || ! is_file($real) || ! str_starts_with($real, $root.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $real;
    }

    public function isAbsoluteReference(string $reference): bool
    {
        return str_starts_with($reference, '/')
            || str_starts_with($reference, '//')
            || preg_match('#^[a-z][a-z0-9+\-.]*:#i', $reference) === 1
            || str_starts_with($reference, '#')
            || str_starts_with($reference, 'data:')
            || str_starts_with($reference, 'mailto:');
    }

    protected function mapToDesignSystemsPath(string $mockupRoot, string $relativePath, ?string $system = null): ?string
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        $basename = basename($relativePath);

        if ($basename === 'tokens.css') {
            $system ??= $this->detectDesignSystem($mockupRoot);

            return $system !== null ? $system.'/tokens.css' : null;
        }

        $mapped = $this->mapTokenBasenameToDesignSystemsPath($basename);

        if ($mapped !== null) {
            return $mapped;
        }

        if (str_starts_with($relativePath, 'design-systems/')) {
            return substr($relativePath, strlen('design-systems/'));
        }

        if (str_starts_with($relativePath, '.larapilot/design-systems/')) {
            return substr($relativePath, strlen('.larapilot/design-systems/'));
        }

        if (preg_match('#(^|/)\.larapilot/design-systems/(.+)$#', $relativePath, $matches) === 1) {
            return $matches[2];
        }

        return null;
    }

    protected function mapTokenBasenameToDesignSystemsPath(string $basename): ?string
    {
        $system = $this->designSystemForTokenBasename($basename);

        return $system !== null ? $system.'/tokens.css' : null;
    }

    protected function detectDesignSystem(string $mockupRoot): ?string
    {
        foreach ($this->designSystemSlugs() as $slug) {
            if (is_file($mockupRoot.DIRECTORY_SEPARATOR.$slug.'-tokens.css')) {
                return $slug;
            }
        }

        $readme = $mockupRoot.DIRECTORY_SEPARATOR.'README.md';

        if (is_file($readme)) {
            $content = strtolower((string) file_get_contents($readme));

            foreach ($this->designSystemSlugs() as $slug) {
                if (str_contains($content, strtolower($slug))) {
                    return $slug;
                }
            }
        }

        return null;
    }

    /**
     * @return array{path: string, relative: string}|null
     */
    protected function findMockupFileByBasename(string $mockupRoot, string $basename): ?array
    {
        if (! is_dir($mockupRoot)) {
            return null;
        }

        $realRoot = realpath($mockupRoot);

        if ($realRoot === false) {
            return null;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realRoot, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getFilename() !== $basename) {
                continue;
            }

            $path = $file->getPathname();
            $relative = ltrim(str_replace($realRoot, '', $path), DIRECTORY_SEPARATOR);

            return [
                'path' => $path,
                'relative' => str_replace(DIRECTORY_SEPARATOR, '/', $relative),
            ];
        }

        return null;
    }

    protected function normalizeRelativePath(string $currentDir, string $reference): ?string
    {
        $currentDir = trim(str_replace('\\', '/', $currentDir), '/');
        $reference = str_replace('\\', '/', $reference);
        $segments = $currentDir === '' ? [] : explode('/', $currentDir);

        foreach (explode('/', $reference) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($segments === []) {
                    continue;
                }

                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            return null;
        }

        return implode('/', $segments);
    }
}
