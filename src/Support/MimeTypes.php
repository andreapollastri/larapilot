<?php

declare(strict_types=1);

namespace Larapilot\Support;

/**
 * Shared extension → MIME type map for mockup and design-system file serving.
 */
final class MimeTypes
{
    /**
     * @var array<string, string>
     */
    private const MAP = [
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

    public static function forPath(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return self::MAP[$extension] ?? 'application/octet-stream';
    }
}
