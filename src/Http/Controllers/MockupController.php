<?php

declare(strict_types=1);

namespace Larapilot\Http\Controllers;

use Illuminate\Http\Response;
use Larapilot\Services\ConfigService;
use Larapilot\Support\MockupAssetResolver;
use Larapilot\Support\MockupCssProcessor;
use Larapilot\Support\MockupHtmlProcessor;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MockupController
{
    /**
     * @var array<string, string>
     */
    protected array $mimeTypes = [
        'html' => 'text/html',
        'htm' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'md' => 'text/markdown',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];

    public function __construct(
        protected ConfigService $config,
        protected MockupAssetResolver $assets,
        protected MockupHtmlProcessor $htmlProcessor,
        protected MockupCssProcessor $cssProcessor,
    ) {}

    public function __invoke(string $spec, ?string $path = null): Response|BinaryFileResponse
    {
        if (! $this->config->mockupsBrowsable()) {
            abort(404);
        }

        if (! $this->isValidSpec($spec)) {
            abort(404);
        }

        if ($path === null && str_contains($spec, '.')) {
            $orphan = $this->assets->resolveOrphanAsset($spec);

            if ($orphan !== null) {
                return $this->fileResponse($orphan, $spec);
            }
        }

        $relativePath = $this->resolveRelativePath($path);
        $mockupsRoot = rtrim($this->mockupsRoot(), DIRECTORY_SEPARATOR);
        $specRoot = $mockupsRoot.DIRECTORY_SEPARATOR.$spec;
        $absolutePath = $this->assets->resolveMockupFile($mockupsRoot, $spec.'/'.$relativePath);

        if ($absolutePath === null) {
            $absolutePath = $this->resolveFallbackAsset($specRoot, $spec, $relativePath);
        }

        if ($absolutePath === null || ! is_file($absolutePath)) {
            abort(404);
        }

        if ($this->isHtml($relativePath)) {
            $html = (string) file_get_contents($absolutePath);

            return response(
                $this->htmlProcessor->process($html, $spec, $specRoot, $relativePath),
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        }

        if ($this->isCss($relativePath)) {
            $css = (string) file_get_contents($absolutePath);

            return response(
                $this->cssProcessor->process($css, $spec, $specRoot, $relativePath, $this->detectDesignSystemFromFilename($relativePath)),
                200,
                ['Content-Type' => 'text/css; charset=UTF-8']
            );
        }

        return $this->fileResponse($absolutePath, $relativePath);
    }

    protected function resolveFallbackAsset(string $specRoot, string $spec, string $relativePath): ?string
    {
        $resolved = $this->assets->resolveAssetReference(
            $spec,
            $specRoot,
            dirname(str_replace('\\', '/', $relativePath)) === '.'
                ? ''
                : dirname(str_replace('\\', '/', $relativePath)),
            basename($relativePath),
            $this->detectDesignSystemFromFilename($relativePath)
        );

        return $resolved['path'] ?? null;
    }

    protected function detectDesignSystemFromFilename(string $relativePath): ?string
    {
        $basename = basename($relativePath);

        if (preg_match('/^(filament|starter-kit|bootstrap-5|tailwind|adminlte)-tokens\.css$/', $basename, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    protected function fileResponse(string $absolutePath, string $relativePath): BinaryFileResponse
    {
        return response()->file($absolutePath, [
            'Content-Type' => $this->mimeType($relativePath),
        ]);
    }

    protected function isValidSpec(string $spec): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $spec);
    }

    protected function resolveRelativePath(?string $path): string
    {
        $path = trim($path ?? '', '/');

        if ($path === '') {
            return 'index.html';
        }

        return $path;
    }

    protected function mockupsRoot(): string
    {
        return $this->assets->mockupsRoot();
    }

    protected function isHtml(string $relativePath): bool
    {
        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        return in_array($extension, ['html', 'htm'], true);
    }

    protected function isCss(string $relativePath): bool
    {
        return strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)) === 'css';
    }

    protected function mimeType(string $relativePath): string
    {
        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        return $this->mimeTypes[$extension] ?? 'application/octet-stream';
    }
}
