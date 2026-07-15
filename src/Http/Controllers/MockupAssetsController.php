<?php

declare(strict_types=1);

namespace Larapilot\Http\Controllers;

use Illuminate\Http\Response;
use Larapilot\Services\ConfigService;
use Larapilot\Support\MockupAssetResolver;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MockupAssetsController
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
    ) {}

    public function __invoke(string $path): Response|BinaryFileResponse
    {
        if (! $this->config->mockupsBrowsable()) {
            abort(404);
        }

        $absolutePath = $this->assets->resolveDesignSystemsFile($path);

        if ($absolutePath === null) {
            abort(404);
        }

        return response()->file($absolutePath, [
            'Content-Type' => $this->mimeType($path),
        ]);
    }

    protected function mimeType(string $relativePath): string
    {
        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        return $this->mimeTypes[$extension] ?? 'application/octet-stream';
    }
}
