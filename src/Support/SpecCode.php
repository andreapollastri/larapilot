<?php

declare(strict_types=1);

namespace Larapilot\Support;

final class SpecCode
{
    /**
     * Mirrors the mockup route constraint so a spec code is always
     * safe to embed in filesystem paths and URLs.
     */
    public const PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]*$/';

    public static function isValid(string $code): bool
    {
        return preg_match(self::PATTERN, $code) === 1 && ! str_contains($code, '..');
    }

    public static function ensure(string $code): string
    {
        if (! self::isValid($code)) {
            throw new \RuntimeException("Invalid spec code: {$code}.");
        }

        return $code;
    }
}
