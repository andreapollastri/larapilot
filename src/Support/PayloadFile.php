<?php

declare(strict_types=1);

namespace Larapilot\Support;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Shared `--file` payload parsing for commands that accept a YAML or
 * JSON document (specs, plans, rework feedback, validations).
 */
final class PayloadFile
{
    /**
     * @return array<string, mixed>|null Null when the file is missing or does not decode to an array.
     */
    public static function parse(?string $path): ?array
    {
        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        try {
            $payload = $extension === 'json'
                ? json_decode($raw, true)
                : Yaml::parse($raw);
        } catch (ParseException) {
            return null;
        }

        return is_array($payload) ? $payload : null;
    }
}
