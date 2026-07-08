<?php

declare(strict_types=1);

namespace Larapilot\Support;

final class Envelope
{
    public const SCHEMA = 'larapilot/v1';

    /**
     * @param  array<string, mixed>  $data
     */
    public static function success(string $kind, array $data): string
    {
        return json_encode([
            'schema' => self::SCHEMA,
            'kind' => $kind,
            'data' => $data,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<string, mixed>|null  $details
     */
    public static function error(string $code, string $message, ?string $hint = null, ?array $details = null): string
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($hint !== null) {
            $error['hint'] = $hint;
        }

        if ($details !== null) {
            $error['details'] = $details;
        }

        return json_encode([
            'schema' => self::SCHEMA,
            'kind' => 'error',
            'error' => $error,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
