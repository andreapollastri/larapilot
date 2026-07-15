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
     * @var list<string>
     */
    protected array $designSystemSlugs = [
        'filament',
        'starter-kit',
        'bootstrap-5',
        'tailwind',
        'adminlte',
    ];

    public function __construct(protected ConfigService $config) {}

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

        foreach ($this->designSystemSlugs as $slug) {
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

        foreach (glob($this->mockupsRoot().DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.$filename) ?: [] as $match) {
            if (is_file($match)) {
                return $match;
            }
        }

        foreach (glob($this->mockupsRoot().DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.$filename) ?: [] as $match) {
            if (is_file($match)) {
                return $match;
            }
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->mockupsRoot(), RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === $filename) {
                return $file->getPathname();
            }
        }

        return $this->resolveDesignSystemsFileByBasename($filename);
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
        if (! str_ends_with($basename, '-tokens.css')) {
            return null;
        }

        $system = str_replace('-tokens.css', '', $basename);

        if (in_array($system, $this->designSystemSlugs, true)) {
            return $system.'/tokens.css';
        }

        return null;
    }

    protected function detectDesignSystem(string $mockupRoot): ?string
    {
        foreach ($this->designSystemSlugs as $slug) {
            if (is_file($mockupRoot.DIRECTORY_SEPARATOR.$slug.'-tokens.css')) {
                return $slug;
            }
        }

        $readme = $mockupRoot.DIRECTORY_SEPARATOR.'README.md';

        if (is_file($readme)) {
            $content = strtolower((string) file_get_contents($readme));

            foreach ($this->designSystemSlugs as $slug) {
                if (str_contains($content, $slug)) {
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
