<?php

declare(strict_types=1);

namespace Larapilot\Services\Tracker;

use RuntimeException;

/**
 * A provider call that failed in a way the operator needs to see. Messages
 * are surfaced verbatim in the CLI envelope, so drivers must never build one
 * from a credential.
 */
class TrackerException extends RuntimeException
{
    public static function api(string $provider, string $action, int $status, string $body): self
    {
        $detail = trim($body);

        if (mb_strlen($detail) > 300) {
            $detail = mb_substr($detail, 0, 297).'…';
        }

        return new self(
            ucfirst($provider).' rejected '.$action.' (HTTP '.$status.')'
                .($detail === '' ? '.' : ': '.$detail)
        );
    }

    public static function unreachable(string $provider, string $action, string $reason): self
    {
        return new self(ucfirst($provider).' is unreachable during '.$action.': '.$reason);
    }
}
