<?php

declare(strict_types=1);

namespace Larapilot\Http\Controllers;

use Illuminate\Http\Response;
use Larapilot\Services\ConfigService;
use Larapilot\Support\MimeTypes;
use Larapilot\Support\MockupAssetResolver;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MockupAssetsController
{
    public function __construct(
        protected ConfigService $config,
        protected MockupAssetResolver $assets,
    ) {}

    public function __invoke(string $path): Response|BinaryFileResponse
    {
        if (! $this->config->mockupAssetsBrowsable()) {
            abort(404);
        }

        $absolutePath = $this->assets->resolveDesignSystemsFile($path);

        if ($absolutePath === null) {
            abort(404);
        }

        return response()->file($absolutePath, [
            'Content-Type' => MimeTypes::forPath($path),
        ]);
    }
}
