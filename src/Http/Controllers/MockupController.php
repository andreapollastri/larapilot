<?php

declare(strict_types=1);

namespace Larapilot\Http\Controllers;

use Illuminate\Http\Response;
use Larapilot\Services\ConfigService;
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
    ];

    public function __construct(protected ConfigService $config) {}

    public function __invoke(string $spec, ?string $path = null): Response|BinaryFileResponse
    {
        if (! $this->config->mockupsBrowsable()) {
            abort(404);
        }

        if (! $this->isValidSpec($spec)) {
            abort(404);
        }

        $relativePath = $this->resolveRelativePath($path);
        $absolutePath = $this->resolveAbsolutePath($spec, $relativePath);

        if ($absolutePath === null || ! is_file($absolutePath)) {
            abort(404);
        }

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

    protected function resolveAbsolutePath(string $spec, string $relativePath): ?string
    {
        if (str_contains($relativePath, "\0") || str_contains($relativePath, '..')) {
            return null;
        }

        $root = rtrim($this->mockupsRoot(), DIRECTORY_SEPARATOR);
        $absolute = $root.DIRECTORY_SEPARATOR.$spec.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $realRoot = realpath($root);
        $realFile = realpath($absolute);

        if ($realRoot === false || $realFile === false) {
            return null;
        }

        if (! str_starts_with($realFile, $realRoot.DIRECTORY_SEPARATOR) && $realFile !== $realRoot) {
            return null;
        }

        return $realFile;
    }

    protected function mockupsRoot(): string
    {
        $config = $this->config->resolve();

        return $this->config->absolutePath($config['paths']['mockups'] ?? '.larapilot/mockups/');
    }

    protected function mimeType(string $relativePath): string
    {
        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        return $this->mimeTypes[$extension] ?? 'application/octet-stream';
    }
}
