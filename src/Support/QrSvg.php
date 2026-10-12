<?php

declare(strict_types=1);

namespace Larapilot\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * A QR code as inline SVG — drawn here, never fetched from a service, so
 * the secret of an authenticator never leaves the server.
 */
final class QrSvg
{
    public static function render(string $text, int $size = 220): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($size, 0), new SvgImageBackEnd)))->writeString($text);

        return trim((string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg));
    }
}
